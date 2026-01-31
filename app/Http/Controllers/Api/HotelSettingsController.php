<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\RoomFeature;
use App\Models\HotelFacility;
use App\Models\HotelGallery;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class HotelSettingsController extends Controller
{
    /**
     * Get hotel ID from authenticated user
     */
    private function getHotelId()
    {
        return auth()->user()->hotel_id;
    }

    /**
     * Get hotel settings (about, features, facilities, galleries)
     * Can be called as public (by hotelId param) or admin (uses auth user's hotel_id)
     */
    public function show(Request $request, ?string $hotelId = null)
    {
        try {
            // Use URL param if provided (public route), otherwise get from auth user (admin route)
            $resolvedHotelId = $hotelId ?? $this->getHotelId();
            
            $hotel = Hotel::findOrFail($resolvedHotelId);

            // Determine whether this is a public (user) request or an admin request.
            $isPublic = $hotelId !== null;

            // Room features: for public requests only return active features at query level.
            $roomFeaturesQuery = $hotel->roomFeatures();
            if ($isPublic) $roomFeaturesQuery->where('is_active', true);
            $roomFeatures = $roomFeaturesQuery->get()->map(function ($f) {
                return [
                    'id' => $f->id,
                    'hotel_id' => $f->hotel_id,
                    'feature_title' => $f->feature_title,
                    'feature_icon' => $f->feature_icon,
                    'is_active' => (bool) $f->is_active,
                    'created_at' => $f->created_at,
                    'updated_at' => $f->updated_at,
                ];
            });

            // Facilities: for public requests only return active facilities at query level.
            $facilitiesQuery = $hotel->facilities();
            if ($isPublic) $facilitiesQuery->where('is_active', true);
            $facilities = $facilitiesQuery->get()->map(function ($ff) {
                return [
                    'id' => $ff->id,
                    'hotel_id' => $ff->hotel_id,
                    'facility_name' => $ff->facility_name,
                    'facility_icon' => $ff->facility_icon,
                    'is_active' => (bool) $ff->is_active,
                    'created_at' => $ff->created_at,
                    'updated_at' => $ff->updated_at,
                ];
            });

            // Only include active galleries for public requests; admin sees all.
            $galleriesQuery = HotelGallery::where('hotel_id', $resolvedHotelId);
            if ($isPublic) $galleriesQuery->where('is_active', true);
            $galleries = $galleriesQuery->orderBy('sort_order')
                ->get()
                ->map(function ($g) {
                    if (empty($g->image_path) || !Storage::disk('public')->exists($g->image_path)) {
                        Log::warning('Missing gallery file for hotel gallery', ['id' => $g->id, 'path' => $g->image_path]);
                        return null;
                    }

                    return [
                        'id' => $g->id,
                        'hotel_id' => $g->hotel_id,
                        'image_path' => $g->image_path,
                        'image_url' => asset('storage/' . $g->image_path),
                        'is_banner_image' => (bool) $g->is_banner_image,
                        'is_active' => (bool) $g->is_active,
                        'sort_order' => $g->sort_order,
                        'created_at' => $g->created_at,
                        'updated_at' => $g->updated_at,
                    ];
                })
                ->filter()
                ->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'about_hotel' => $hotel->about_hotel,
                    'room_features' => $roomFeatures,
                    'facilities' => $facilities,
                    'galleries' => $galleries,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch hotel settings',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Update about hotel text
     */
    public function updateAbout(Request $request)
    {
        try {
            $hotelId = $this->getHotelId();
            
            $validated = $request->validate([
                'about_hotel' => 'required|string|max:5000',
            ]);

            $hotel = Hotel::findOrFail($hotelId);
            $hotel->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Hotel about section updated successfully',
                'data' => $hotel,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update hotel about',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add room feature
     */
    public function addRoomFeature(Request $request)
    {
        try {
            $hotelId = $this->getHotelId();
            
            $validated = $request->validate([
                'feature_title' => 'required|string|max:255',
                'feature_icon' => 'nullable|string|max:255',
            ]);

            Hotel::findOrFail($hotelId);

            $feature = RoomFeature::create([
                'hotel_id' => $hotelId,
                ...$validated,
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Room feature added successfully',
                'data' => $feature,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add room feature',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete room feature
     */
    public function deleteRoomFeature(string $featureId)
    {
        try {
            $hotelId = $this->getHotelId();
            
            Hotel::findOrFail($hotelId);
            $feature = RoomFeature::where('id', $featureId)
                ->where('hotel_id', $hotelId)
                ->firstOrFail();

            $feature->delete();

            return response()->json([
                'success' => true,
                'message' => 'Room feature deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete room feature',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle active/inactive state for a room feature (admin only)
     */
    public function toggleRoomFeatureActive(string $featureId)
    {
        try {
            $hotelId = $this->getHotelId();

            $feature = RoomFeature::where('id', $featureId)
                ->where('hotel_id', $hotelId)
                ->firstOrFail();

            $feature->is_active = !$feature->is_active;
            $feature->save();

            return response()->json([
                'success' => true,
                'message' => 'Room feature updated successfully',
                'data' => [
                    'id' => $feature->id,
                    'feature_title' => $feature->feature_title,
                    'feature_icon' => $feature->feature_icon,
                    'is_active' => (bool) $feature->is_active,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update room feature',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add hotel facility
     */
    public function addFacility(Request $request)
    {
        try {
            $hotelId = $this->getHotelId();
            
            $validated = $request->validate([
                'facility_name' => 'required|string|max:255',
                'facility_icon' => 'required|string|max:255',
            ]);

            Hotel::findOrFail($hotelId);

            $facility = HotelFacility::create([
                'hotel_id' => $hotelId,
                ...$validated,
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Facility added successfully',
                'data' => $facility,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add facility',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete hotel facility
     */
    public function deleteFacility(string $facilityId)
    {
        try {
            $hotelId = $this->getHotelId();
            
            Hotel::findOrFail($hotelId);
            $facility = HotelFacility::where('id', $facilityId)
                ->where('hotel_id', $hotelId)
                ->firstOrFail();

            $facility->delete();

            return response()->json([
                'success' => true,
                'message' => 'Facility deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete facility',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle active/inactive state for a facility (admin only)
     */
    public function toggleFacilityActive(string $facilityId)
    {
        try {
            $hotelId = $this->getHotelId();

            $facility = HotelFacility::where('id', $facilityId)
                ->where('hotel_id', $hotelId)
                ->firstOrFail();

            $facility->is_active = !$facility->is_active;
            $facility->save();

            return response()->json([
                'success' => true,
                'message' => 'Facility updated successfully',
                'data' => [
                    'id' => $facility->id,
                    'facility_name' => $facility->facility_name,
                    'facility_icon' => $facility->facility_icon,
                    'is_active' => (bool) $facility->is_active,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update facility',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload multiple gallery images
     */
    public function uploadGalleryImages(Request $request)
    {
        try {
            $hotelId = $this->getHotelId();
            
            // Do not restrict image size/width/height per requirements
            $validated = $request->validate([
                'images' => 'required|array',
                'images.*' => 'required|image|mimes:jpeg,png,jpg,gif,webp',
                'is_banner' => 'nullable|boolean',
            ]);

            Hotel::findOrFail($hotelId);

            $uploadedImages = [];

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    // Store image in public/hotel-galleries folder
                    $path = $image->store('hotel-galleries', 'public');

                    // Get the highest sort_order and increment
                    $maxSortOrder = HotelGallery::where('hotel_id', $hotelId)->max('sort_order') ?? 0;

                    $galleryImage = HotelGallery::create([
                        'hotel_id' => $hotelId,
                        'image_path' => $path,
                        'is_banner_image' => $validated['is_banner'] ?? false,
                        'is_active' => true,
                        'sort_order' => $maxSortOrder + 1,
                    ]);

                    // Only include if file exists on disk (should be true immediately after store)
                    if (Storage::disk('public')->exists($galleryImage->image_path)) {
                        $uploadedImages[] = [
                            'id' => $galleryImage->id,
                            'hotel_id' => $galleryImage->hotel_id,
                            'image_path' => $galleryImage->image_path,
                            'image_url' => asset('storage/' . $galleryImage->image_path),
                            'is_banner_image' => (bool) $galleryImage->is_banner_image,
                            'is_active' => (bool) $galleryImage->is_active,
                            'sort_order' => $galleryImage->sort_order,
                            'created_at' => $galleryImage->created_at,
                            'updated_at' => $galleryImage->updated_at,
                        ];
                    } else {
                        Log::warning('Uploaded file not found on disk after store', ['path' => $galleryImage->image_path]);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Gallery images uploaded successfully',
                'data' => $uploadedImages,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload gallery images',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle active/inactive state for a gallery image
     */
    public function toggleGalleryActive(string $imageId)
    {
        try {
            $hotelId = $this->getHotelId();

            $galleryImage = HotelGallery::where('id', $imageId)
                ->where('hotel_id', $hotelId)
                ->firstOrFail();

            $galleryImage->is_active = !$galleryImage->is_active;

            // If deactivating and it was banner, unset banner flag
            if (!$galleryImage->is_active && $galleryImage->is_banner_image) {
                $galleryImage->is_banner_image = false;
            }

            $galleryImage->save();

            // Provide fully-qualified image_url when available
            $data = [
                'id' => $galleryImage->id,
                'hotel_id' => $galleryImage->hotel_id,
                'image_path' => $galleryImage->image_path,
                'is_banner_image' => (bool) $galleryImage->is_banner_image,
                'is_active' => (bool) $galleryImage->is_active,
                'sort_order' => $galleryImage->sort_order,
                'created_at' => $galleryImage->created_at,
                'updated_at' => $galleryImage->updated_at,
            ];

            if ($galleryImage->is_active && !empty($galleryImage->image_path) && Storage::disk('public')->exists($galleryImage->image_path)) {
                $data['image_url'] = asset('storage/' . $galleryImage->image_path);
            } else {
                if (!empty($galleryImage->image_path) && !Storage::disk('public')->exists($galleryImage->image_path)) {
                    Log::warning('Gallery image toggled but file missing', ['id' => $galleryImage->id, 'path' => $galleryImage->image_path]);
                }
                $data['image_url'] = null;
            }

            return response()->json([
                'success' => true,
                'message' => 'Gallery image updated successfully',
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update gallery image',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Set a gallery image as the banner for the hotel. Only one banner allowed.
     */
    public function setBannerImage(string $imageId)
    {
        try {
            $hotelId = $this->getHotelId();

            // Use transaction to ensure only one banner
            \DB::beginTransaction();

            $target = HotelGallery::where('id', $imageId)
                ->where('hotel_id', $hotelId)
                ->firstOrFail();

            // Do not allow inactive image to be banner
            if (!$target->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot set an inactive image as banner',
                ], 422);
            }

            // Unset banner flag for all images of this hotel
            HotelGallery::where('hotel_id', $hotelId)->update(['is_banner_image' => false]);

            // Set target as banner
            $target->is_banner_image = true;
            $target->save();

            \DB::commit();

            // Transform target and return current active galleries (with image_url)
            $targetData = [
                'id' => $target->id,
                'hotel_id' => $target->hotel_id,
                'image_path' => $target->image_path,
                'is_banner_image' => (bool) $target->is_banner_image,
                'is_active' => (bool) $target->is_active,
                'sort_order' => $target->sort_order,
                'created_at' => $target->created_at,
                'updated_at' => $target->updated_at,
            ];

            if ($target->is_active && !empty($target->image_path) && Storage::disk('public')->exists($target->image_path)) {
                $targetData['image_url'] = asset('storage/' . $target->image_path);
            } else {
                $targetData['image_url'] = null;
                if (!empty($target->image_path) && !Storage::disk('public')->exists($target->image_path)) {
                    Log::warning('Banner image set but file missing', ['id' => $target->id, 'path' => $target->image_path]);
                }
            }

            $all = HotelGallery::where('hotel_id', $hotelId)->where('is_active', true)->orderBy('sort_order')->get()
                ->map(function ($g) {
                    if (empty($g->image_path) || !Storage::disk('public')->exists($g->image_path)) {
                        Log::warning('Missing gallery file when collecting all galleries', ['id' => $g->id, 'path' => $g->image_path]);
                        return null;
                    }

                    return [
                        'id' => $g->id,
                        'hotel_id' => $g->hotel_id,
                        'image_path' => $g->image_path,
                        'image_url' => asset('storage/' . $g->image_path),
                        'is_banner_image' => (bool) $g->is_banner_image,
                        'is_active' => (bool) $g->is_active,
                        'sort_order' => $g->sort_order,
                        'created_at' => $g->created_at,
                        'updated_at' => $g->updated_at,
                    ];
                })->filter()->values();

            return response()->json([
                'success' => true,
                'message' => 'Banner image set successfully',
                'data' => $targetData,
                'all_galleries' => $all,
            ]);
        } catch (\Exception $e) {
            \DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to set banner image',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete gallery image
     */
    public function deleteGalleryImage(string $imageId)
    {
        try {
            $hotelId = $this->getHotelId();
            
            Hotel::findOrFail($hotelId);
            $galleryImage = HotelGallery::where('id', $imageId)
                ->where('hotel_id', $hotelId)
                ->firstOrFail();

            // Delete file from storage using Storage facade; log if missing
            if (!empty($galleryImage->image_path)) {
                if (Storage::disk('public')->exists($galleryImage->image_path)) {
                    Storage::disk('public')->delete($galleryImage->image_path);
                } else {
                    Log::warning('Attempted to delete gallery file but file not found', ['id' => $galleryImage->id, 'path' => $galleryImage->image_path]);
                }
            }

            $galleryImage->delete();

            return response()->json([
                'success' => true,
                'message' => 'Gallery image deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete gallery image',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update gallery image sort order
     */
    public function updateGallerySortOrder(Request $request)
    {
        try {
            $hotelId = $this->getHotelId();
            
            $validated = $request->validate([
                'images' => 'required|array',
                'images.*.id' => 'required|integer',
                'images.*.sort_order' => 'required|integer|min:0',
            ]);

            Hotel::findOrFail($hotelId);

            foreach ($validated['images'] as $imageData) {
                HotelGallery::where('id', $imageData['id'])
                    ->where('hotel_id', $hotelId)
                    ->update(['sort_order' => $imageData['sort_order']]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Gallery sort order updated successfully',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update sort order',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getHotelName()
    {
        try {
            $hotelId = $this->getHotelId();
            $hotel = Hotel::findOrFail($hotelId);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $hotel->id,
                    'name' => $hotel->name,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch hotel name',
                'error' => $e->getMessage(),
            ], 404);
        }
    }   
}
