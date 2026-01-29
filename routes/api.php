<?php
use App\Http\Controllers\HotelController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminRoomController;
use App\Http\Controllers\Api\AdminServiceController;
use App\Http\Controllers\Api\AdminBookingController;
use App\Http\Controllers\Api\AdminBookingServiceController;
use App\Http\Controllers\Api\AdminReportController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\UserAuthController;
use App\Http\Controllers\Api\SuperAdminBookingController;
use App\Http\Controllers\Api\ReviewController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AdminAuthController::class, 'login']);

/* ========================================
   PUBLIC USER AUTHENTICATION ROUTES
   ======================================== */
Route::prefix('user')->group(function () {
    // User Signup with OTP Verification
    Route::post('/send-otp', [UserAuthController::class, 'sendOtp']);
    Route::post('/verify-otp', [UserAuthController::class, 'verifyOtp']);
    Route::post('/resend-otp', [UserAuthController::class, 'resendOtp']);
    
    // User Login
    Route::post('/login', [UserAuthController::class, 'login']);
    
    // PUBLIC HOTEL SEARCH & BOOKING ROUTES
    Route::get('/search-cities', [UserController::class, 'searchCities']);
    Route::post('/search-hotels', [UserController::class, 'searchHotels']);
    Route::get('/hotels/{hotelId}', [UserController::class, 'getHotelDetails']);
    Route::post('/check-room-availability', [UserController::class, 'checkRoomAvailability']);
    Route::get('/hotels/{hotelId}/reviews', [ReviewController::class, 'getHotelReviews']);
    Route::get('/reviews', [ReviewController::class, 'getReviewsByHotelId']); // Query parameter endpoint
    
    // Protected user routes - requires authentication
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/profile', [UserController::class, 'profile']); // User profile API
        Route::post('/create-booking', [UserController::class, 'createBooking']);
        Route::get('/bookings', [UserController::class, 'getUserBookings']);
        Route::get('/bookings/{id}', [UserController::class, 'getUserBooking']);
        Route::put('/bookings/{id}', [UserController::class, 'updateUserBooking']);
        Route::put('/bookings/{id}/cancel', [UserController::class, 'cancelUserBooking']);
        Route::get('/bookings/{id}/services', [UserController::class, 'getUserBookingServices']);
        Route::get('/bookings/{id}/guests', [UserController::class, 'getUserBookingGuests']);
        
        // Review routes
        Route::post('/reviews', [ReviewController::class, 'store']);
        Route::get('/reviews/booking/{bookingId}', [ReviewController::class, 'getByBooking']);
        Route::put('/reviews/{reviewId}', [ReviewController::class, 'update']);
        Route::delete('/reviews/{reviewId}', [ReviewController::class, 'destroy']);
    });
});

Route::middleware(['auth:sanctum', 'admin'])
    ->prefix('admin')
    ->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout']);

        // Dashboard
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);

        // Rooms CRUD
        Route::apiResource('rooms', AdminRoomController::class);

        // Services CRUD
        Route::apiResource('services', AdminServiceController::class);

        // Users (for current hotel)
        Route::get('users', [AdminUserController::class, 'index']);
        Route::get('users/{user}', [AdminUserController::class, 'show']);
        Route::put('users/{user}', [AdminUserController::class, 'update']);
        Route::delete('users/{user}', [AdminUserController::class, 'destroy']);

        // Bookings CRUD
        Route::get('bookings', [AdminBookingController::class, 'index']);
        Route::get('bookings/{booking}', [AdminBookingController::class, 'show']); // ✅ REQUIRED
        Route::post('bookings', [AdminBookingController::class, 'store']);
        Route::put('bookings/{booking}', [AdminBookingController::class, 'update']);
        Route::delete('bookings/{booking}', [AdminBookingController::class, 'destroy']);
        Route::put('bookings/cancel/{booking}', [AdminBookingController::class, 'cancelBooking']);
        Route::post('bookings/{booking}/add-payment', 
    [AdminBookingController::class, 'addPayment']
);
        Route::put('bookings/{booking}/change-room', [AdminBookingController::class, 'changeRoom']);
        Route::get('bookings/{booking}/room-changes', [AdminBookingController::class, 'getRoomChanges']);
        Route::patch('bookings/{booking}/confirm', [AdminBookingController::class, 'confirmBooking']);
        Route::patch('bookings/{booking}/checkout', [AdminBookingController::class, 'checkoutBooking']);
        Route::patch('bookings/{booking}/check-in', [AdminBookingController::class, 'checkInBooking']);

        Route::get('available-rooms', [AdminBookingController::class, 'availableRooms']);
        Route::get('available-rooms-for-change', [AdminBookingController::class, 'availableRoomsForRoomChange']);


        // Booking Services CRUD

        Route::get('bookings/{booking}/services', [AdminBookingServiceController::class, 'index']);
        Route::post('bookings/{booking}/services', [AdminBookingServiceController::class, 'store']);
        Route::put('bookings/{booking}/services/{bookingService}', [AdminBookingServiceController::class, 'update']);
        Route::delete('bookings/{booking}/services/{bookingService}', [AdminBookingServiceController::class, 'destroy']);


        // Guests (derived from bookings)
        Route::get('guests', [AdminBookingController::class, 'guests']);

        // Reports
        Route::get('reports', [AdminReportController::class, 'index']);
        Route::get('reports/download', [AdminReportController::class, 'downloadReport']);

        // Reviews (admin can view and delete)
        Route::get('reviews', [ReviewController::class, 'adminGetHotelReviews']);
        Route::delete('reviews/{reviewId}', [ReviewController::class, 'adminDeleteReview']);
    });

Route::middleware(['auth:sanctum', 'superadmin'])
    ->prefix('superadmin')
    ->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout']);
        Route::get('/dashboard', [HotelController::class, 'getHotelDashboard']);
        Route::get('/hotel/{id}', [HotelController::class, 'getHotelData']);
        Route::put('/hotel/{id}', [HotelController::class, 'updateHotel']);
        Route::delete('/hotel/{id}', [HotelController::class, 'deleteHotel']);
        Route::post('/hotel/{id}/users', [HotelController::class, 'addUser']);
        Route::put('/hotel/{id}/users/{userId}', [HotelController::class, 'updateUser']);
        Route::delete('/hotel/{id}/users/{userId}', [HotelController::class, 'deleteUser']);

        // Bookings Management for Super Admin
        Route::get('bookings/statistics', [SuperAdminBookingController::class, 'statistics']);
        Route::get('bookings/hotel/{hotel}', [SuperAdminBookingController::class, 'getByHotel']);
        Route::get('bookings', [SuperAdminBookingController::class, 'index']);
        Route::get('bookings/{booking}', [SuperAdminBookingController::class, 'show']);
        Route::put('bookings/{booking}', [SuperAdminBookingController::class, 'update']);
        Route::post('bookings/{booking}/add-payment', [SuperAdminBookingController::class, 'addPayment']);
        Route::patch('bookings/{booking}/status', [SuperAdminBookingController::class, 'updateStatus']);
    });

    Route::get('/hotel/dashboard',[HotelController::class,'getHotelDashboard']);
    Route::get('/hotel', [HotelController::class, 'getHotel']);
    Route::post('/hotel', [HotelController::class, 'storeHotel']);
    Route::get('/hotel/cities/search', [HotelController::class, 'searchCities']);
    Route::put('/superadmin/hotel/{id}/status', [HotelController::class, 'toggleStatus']);
