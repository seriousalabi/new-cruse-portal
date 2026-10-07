<?php

use App\Models\User;
use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\TeacherResultController;
use App\Http\Controllers\AdminResultController;
use App\Http\Controllers\ParentResultController;
use App\Http\Controllers\AccountController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

Route::redirect('/', '/login');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', fn () => view('auth.login'))->name('login');

    Route::post('/login', function (Request $request) {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $credentials['account_status'] = 'active';

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'These sign-in details could not be verified, or the account is inactive.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    })->middleware('throttle:login')->name('login.store');
});

Route::middleware(['auth', 'active.account', 'password.change'])->group(function (): void {
    Route::get('/account/change-password', [AccountController::class, 'changePasswordForm'])->withoutMiddleware('password.change')->name('password.change');
    Route::post('/account/change-password', [AccountController::class, 'changePassword'])->withoutMiddleware('password.change')->name('password.change.store');
    Route::get('/dashboard', function (Request $request) {
        /** @var User $user */
        $user = $request->user();
        $role = collect(['super_admin', 'admin', 'teacher', 'parent'])
            ->first(fn (string $roleKey) => $user->hasRole($roleKey));

        abort_unless($role, 403, 'This account has no portal role. Contact the school administrator.');

        return view('dashboard', ['role' => $role]);
    })->name('dashboard');

    Route::middleware('permission:classes.manage')->prefix('school')->name('school.')->group(function (): void {
        Route::get('/academic-years', [AcademicYearController::class, 'index'])->name('academic-years.index');
        Route::post('/academic-years', [AcademicYearController::class, 'store'])->name('academic-years.store');
        Route::post('/academic-years/{academicYear}/activate', [AcademicYearController::class, 'activate'])->name('academic-years.activate');
    });

    Route::middleware('permission:students.manage')->group(function (): void {
        Route::get('/students', [StudentController::class, 'index'])->name('students.index');
        Route::post('/students', [StudentController::class, 'store'])->name('students.store');
    });

    Route::middleware('permission:parents.manage')->prefix('families')->name('families.')->group(function (): void {
        Route::get('/', [FamilyController::class, 'index'])->name('index');
        Route::post('/', [FamilyController::class, 'store'])->name('store');
        Route::post('/link-existing', [FamilyController::class, 'linkExisting'])->name('link-existing');
        Route::patch('/{parentProfile}', [FamilyController::class, 'update'])->name('update');
        Route::delete('/{parentProfile}/students/{studentProfile}', [FamilyController::class, 'closeLink'])->name('links.close');
    });

    Route::middleware('permission:staff.manage')->group(function (): void {
        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
        Route::patch('/staff/{staffProfile}', [StaffController::class, 'update'])->name('staff.update');
        Route::patch('/staff/{staffProfile}/end', [StaffController::class, 'endStaffProfile'])->name('staff.end');
    });

    Route::middleware('permission:accounts.manage')->prefix('accounts')->name('accounts.')->group(function (): void {
        Route::get('/', [AccountController::class, 'index'])->name('index');
        Route::post('/', [AccountController::class, 'create'])->name('create');
    });

    Route::middleware('permission:results.enter-assigned')->prefix('teacher/results')->name('teacher.results.')->group(function (): void {
        Route::get('/', [TeacherResultController::class, 'index'])->name('index');
        Route::get('/{assignment}/{term}/{subject}', [TeacherResultController::class, 'edit'])->whereNumber('assignment')->whereNumber('term')->whereNumber('subject')->name('edit');
        Route::post('/{assignment}/{term}/{subject}', [TeacherResultController::class, 'save'])->whereNumber('assignment')->whereNumber('term')->whereNumber('subject')->middleware('permission:results.submit-assigned')->name('save');
    });

    Route::middleware('permission:results.review')->prefix('admin/results')->name('admin.results.')->group(function (): void {
        Route::get('/', [AdminResultController::class, 'index'])->name('index');
        Route::get('/corrections', [AdminResultController::class, 'correctionRequests'])->name('corrections');
        Route::post('/corrections/{correctionRequest}/respond', [AdminResultController::class, 'respondToCorrection'])->whereNumber('correctionRequest')->middleware('permission:correction-requests.review')->name('corrections.respond');
        Route::get('/{submission}', [AdminResultController::class, 'show'])->whereNumber('submission')->name('show');
        Route::post('/{submission}/return', [AdminResultController::class, 'returnToTeacher'])->whereNumber('submission')->name('return');
        Route::post('/{submission}/approve', [AdminResultController::class, 'approve'])->whereNumber('submission')->middleware('permission:results.approve')->name('approve');
        Route::post('/{submission}/reopen', [AdminResultController::class, 'reopen'])->whereNumber('submission')->middleware('permission:results.reopen')->name('reopen');
    });

    Route::middleware('permission:results.view-linked')->prefix('parent/results')->name('parent.results.')->group(function (): void {
        Route::get('/', [ParentResultController::class, 'index'])->name('index');
        Route::post('/corrections', [ParentResultController::class, 'requestCorrection'])->name('corrections');
    });

    Route::middleware('permission:classes.manage')->prefix('staff/assignments')->name('staff.assignments.')->group(function (): void {
        Route::post('/', [StaffController::class, 'storeAssignment'])->name('store');
        Route::get('/{assignment}/edit', [StaffController::class, 'editAssignment'])->whereNumber('assignment')->name('edit');
        Route::put('/{assignment}', [StaffController::class, 'updateAssignment'])->whereNumber('assignment')->name('update');
        Route::patch('/{assignment}/end', [StaffController::class, 'endAssignment'])->whereNumber('assignment')->name('end');
    });

    Route::middleware(['permission:students.manage', 'permission:imports.commit'])->prefix('students/import')->name('students.import.')->group(function (): void {
        Route::post('/preview', [StudentController::class, 'previewImport'])->name('preview');
        Route::post('/{batch}/commit', [StudentController::class, 'commitImport'])->whereNumber('batch')->name('commit');
        Route::delete('/{batch}', [StudentController::class, 'discardImport'])->whereNumber('batch')->name('discard');
        Route::get('/{batch}/errors', [StudentController::class, 'downloadImportErrors'])->whereNumber('batch')->name('errors');
    });

    Route::post('/logout', function (Request $request) {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');
});
