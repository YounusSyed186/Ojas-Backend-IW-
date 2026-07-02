<?php

use App\Http\Controllers\Api\Admin\ConsultationController as AdminConsultationController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\MealTemplateController as AdminMealTemplateController;
use App\Http\Controllers\Api\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\Admin\PincodeController as AdminPincodeController;
use App\Http\Controllers\Api\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Api\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Api\Admin\SubscriptionController as AdminSubscriptionController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\CustomerDashboardController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\Doctor\ConsultationController as DoctorConsultationController;
use App\Http\Controllers\Api\Doctor\DashboardController as DoctorDashboardController;
use App\Http\Controllers\Api\Doctor\NotesController as DoctorNotesController;
use App\Http\Controllers\Api\Doctor\PatientController as DoctorPatientController;
use App\Http\Controllers\Api\Doctor\ProfileController as DoctorProfileController;
use App\Http\Controllers\Api\Doctor\ScheduleController as DoctorScheduleController;
use App\Http\Controllers\Api\MealController;
use App\Http\Controllers\Api\MealOptionController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PincodeController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\SubscriptionController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/otp/send', [AuthController::class, 'sendOtp']);
Route::post('/auth/otp/verify', [AuthController::class, 'verifyOtp']);

Route::get('/meals', [MealController::class, 'index']);
Route::get('/meals/{slug}', [MealController::class, 'show']);
Route::get('/categories', [MealController::class, 'categories']);
Route::get('/categories/{slug}', [MealController::class, 'categoryMeals']);

Route::get('/doctors', [DoctorController::class, 'index']);
Route::get('/doctors/{slug}', [DoctorController::class, 'show']);

Route::get('/subscription-plans', [PlanController::class, 'index']);
Route::get('/subscription-plans/{id}', [PlanController::class, 'show']);

Route::get('/meal-templates', [MealOptionController::class, 'templates']);
Route::get('/meal-templates/{id}', [MealOptionController::class, 'templateShow']);
Route::get('/meal-options/{templateId}', [MealOptionController::class, 'optionsByTemplate']);

Route::get('/pincode/validate', [PincodeController::class, 'validate']);
Route::get('/pincode/serviceable', [PincodeController::class, 'serviceable']);

Route::get('/consultation-fee', [ConsultationController::class, 'fee']);

// Protected routes (require auth)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Subscriptions
    Route::post('/subscriptions', [SubscriptionController::class, 'store']);
    Route::get('/subscriptions', [SubscriptionController::class, 'index']);
    Route::get('/subscriptions/{id}', [SubscriptionController::class, 'show']);
    Route::post('/subscriptions/{id}/cancel', [SubscriptionController::class, 'cancel']);
    Route::post('/subscriptions/{id}/pause', [SubscriptionController::class, 'pause']);
    Route::post('/subscriptions/{id}/resume', [SubscriptionController::class, 'resume']);

    // Payments
    Route::post('/payments/razorpay/order', [PaymentController::class, 'createOrder']);
    Route::post('/payments/razorpay/verify', [PaymentController::class, 'verifyPayment']);

    // Customer dashboard
    Route::middleware('user.role:customer')->group(function () {
        Route::get('/customer/dashboard', [CustomerDashboardController::class, 'index']);
        Route::get('/customer/payments', [CustomerDashboardController::class, 'payments']);
        Route::get('/customer/consultations', [CustomerDashboardController::class, 'consultations']);
        Route::get('/customer/profile', [CustomerDashboardController::class, 'profile']);
        Route::put('/customer/profile', [CustomerDashboardController::class, 'updateProfile']);
    });

    // Consultations
    Route::get('/consultations', [ConsultationController::class, 'index']);
    Route::post('/consultations', [ConsultationController::class, 'store']);
    Route::get('/consultations/doctors/available', [ConsultationController::class, 'availableDoctors']);
    Route::get('/consultations/{id}', [ConsultationController::class, 'show']);
    Route::post('/consultations/{id}/cancel', [ConsultationController::class, 'cancel']);
    Route::post('/consultations/{id}/payments/razorpay/order', [PaymentController::class, 'createConsultationOrder']);
    Route::post('/consultations/{id}/payments/razorpay/verify', [PaymentController::class, 'verifyConsultationPayment']);

    // Doctor dashboard
    Route::prefix('doctor')->middleware('user.role:doctor')->group(function () {
        Route::get('/dashboard', [DoctorDashboardController::class, 'index']);
        Route::get('/consultations', [DoctorConsultationController::class, 'index']);
        Route::get('/consultations/{id}', [DoctorConsultationController::class, 'show']);
        Route::post('/consultations/{id}/accept', [DoctorConsultationController::class, 'accept']);
        Route::post('/consultations/{id}/schedule', [DoctorConsultationController::class, 'schedule']);
        Route::post('/consultations/{id}/notes', [DoctorConsultationController::class, 'addNotes']);
        Route::post('/consultations/{id}/assign', [DoctorConsultationController::class, 'assignPlan']);
        Route::post('/consultations/{id}/complete', [DoctorConsultationController::class, 'markCompleted']);
        Route::get('/meal-templates', [DoctorConsultationController::class, 'mealTemplates']);

        // Patients
        Route::get('/patients', [DoctorPatientController::class, 'index']);
        Route::get('/patients/{id}', [DoctorPatientController::class, 'show']);

        // Schedule
        Route::get('/schedule', [DoctorScheduleController::class, 'index']);

        // Notes
        Route::get('/notes', [DoctorNotesController::class, 'index']);

        // Profile
        Route::get('/profile', [DoctorProfileController::class, 'show']);
        Route::put('/profile', [DoctorProfileController::class, 'update']);
    });

    // Admin dashboard
    Route::prefix('admin')->middleware('user.role:admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);

        Route::get('/consultations', [AdminConsultationController::class, 'index']);
        Route::post('/consultations/{id}/assign-doctor', [AdminConsultationController::class, 'assignDoctor']);
        Route::put('/consultations/{id}', [AdminConsultationController::class, 'update']);

        Route::get('/users', [AdminUserController::class, 'index']);
        Route::put('/users/{id}', [AdminUserController::class, 'update']);

        Route::get('/subscriptions', [AdminSubscriptionController::class, 'index']);
        Route::put('/subscriptions/{id}', [AdminSubscriptionController::class, 'update']);

        Route::get('/payments', [AdminPaymentController::class, 'index']);

        Route::get('/plans', [AdminPlanController::class, 'index']);
        Route::put('/plans/{id}', [AdminPlanController::class, 'update']);

        Route::get('/meal-templates', [AdminMealTemplateController::class, 'index']);
        Route::put('/meal-templates/{id}', [AdminMealTemplateController::class, 'update']);

        Route::get('/pincodes', [AdminPincodeController::class, 'index']);
        Route::post('/pincodes', [AdminPincodeController::class, 'store']);
        Route::put('/pincodes/{id}', [AdminPincodeController::class, 'update']);

        Route::get('/settings', [AdminSettingController::class, 'index']);
        Route::put('/settings/{id}', [AdminSettingController::class, 'update']);

        Route::get('/reports', [\App\Http\Controllers\Api\Admin\ReportController::class, 'index']);

        Route::get('/doctors', [\App\Http\Controllers\Api\Admin\AdminDoctorController::class, 'index']);
        Route::post('/doctors', [\App\Http\Controllers\Api\Admin\AdminDoctorController::class, 'store']);
        Route::get('/doctors/{id}', [\App\Http\Controllers\Api\Admin\AdminDoctorController::class, 'show']);
        Route::put('/doctors/{id}', [\App\Http\Controllers\Api\Admin\AdminDoctorController::class, 'update']);
        Route::post('/doctors/{id}/reset-password', [\App\Http\Controllers\Api\Admin\AdminDoctorController::class, 'resetPassword']);
        Route::post('/doctors/{id}/toggle-status', [\App\Http\Controllers\Api\Admin\AdminDoctorController::class, 'toggleStatus']);
    });
});
