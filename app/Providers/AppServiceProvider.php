<?php

namespace App\Providers;

use App\Bouncer\Scopes\DefaultScope;
use App\Models\Invoice;
use App\Observers\InvoiceObserver;
use App\Policies\CompanyPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\DashboardPolicy;
use App\Policies\EstimatePolicy;
use App\Policies\ExpensePolicy;
use App\Policies\InvoicePolicy;
use App\Policies\ItemPolicy;
use App\Policies\ModulesPolicy;
use App\Policies\NotePolicy;
use App\Policies\OwnerPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\RecurringInvoicePolicy;
use App\Policies\ReportPolicy;
use App\Policies\RolePolicy;
use App\Policies\SettingsPolicy;
use App\Policies\UserPolicy;
use App\Space\InstallUtils;
use Gate;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Silber\Bouncer\Database\Models as BouncerModels;
use Silber\Bouncer\Database\Role;
use View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/admin/dashboard';

    /**
     * The path to the "customer home" route for your application.
     *
     * This is used by Laravel authentication to redirect customers after login.
     *
     * @var string
     */
    public const CUSTOMER_HOME = '/customer/dashboard';

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register model observers
        Invoice::observe(InvoiceObserver::class);

        if (InstallUtils::isDbCreated()) {
            $this->addMenus();
        }

        Gate::policy(Role::class, RolePolicy::class);

        View::addNamespace('pdf_templates', storage_path('app/templates/pdf'));

        $this->bootAuth();
        $this->bootBroadcast();
        $this->configureRateLimiting();

        // In demo mode, prevent all outgoing emails and notifications
        if (config('app.env') === 'demo') {
            \Illuminate\Support\Facades\Mail::fake();
            \Illuminate\Support\Facades\Notification::fake();
        }
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        BouncerModels::scope(new DefaultScope);

        // Register services
        $this->app->singleton(\App\Services\InvoiceService::class, function ($app) {
            return new \App\Services\InvoiceService();
        });
    }

    public function addMenus()
    {
        // main menu
        \Menu::make('main_menu', function ($menu) {
            foreach (config('invoiceshelf.main_menu') as $data) {
                $this->generateMenu($menu, $data);
            }
        });

        // setting menu
        \Menu::make('setting_menu', function ($menu) {
            foreach (config('invoiceshelf.setting_menu') as $data) {
                $this->generateMenu($menu, $data);
            }
        });

        \Menu::make('customer_portal_menu', function ($menu) {
            foreach (config('invoiceshelf.customer_menu') as $data) {
                $this->generateMenu($menu, $data);
            }
        });
    }

    public function generateMenu($menu, $data)
    {
        $menu->add($data['title'], $data['link'])
            ->data('icon', $data['icon'])
            ->data('name', $data['name'])
            ->data('owner_only', $data['owner_only'])
            ->data('ability', $data['ability'])
            ->data('model', $data['model'])
            ->data('group', $data['group']);
    }

    public function bootAuth()
    {

        Gate::define('create company', [CompanyPolicy::class, 'create']);
        Gate::define('transfer company ownership', [CompanyPolicy::class, 'transferOwnership']);
        Gate::define('delete company', [CompanyPolicy::class, 'delete']);

        Gate::define('manage modules', [ModulesPolicy::class, 'manageModules']);

        Gate::define('manage settings', [SettingsPolicy::class, 'manageSettings']);
        Gate::define('manage company', [SettingsPolicy::class, 'manageCompany']);
        Gate::define('manage backups', [SettingsPolicy::class, 'manageBackups']);
        Gate::define('manage file disk', [SettingsPolicy::class, 'manageFileDisk']);
        Gate::define('manage email config', [SettingsPolicy::class, 'manageEmailConfig']);
        Gate::define('manage pdf config', [SettingsPolicy::class, 'managePDFConfig']);
        Gate::define('manage notes', [NotePolicy::class, 'manageNotes']);
        Gate::define('view notes', [NotePolicy::class, 'viewNotes']);

        Gate::define('send invoice', [InvoicePolicy::class, 'send']);
        Gate::define('send estimate', [EstimatePolicy::class, 'send']);
        Gate::define('send payment', [PaymentPolicy::class, 'send']);

        Gate::define('delete multiple items', [ItemPolicy::class, 'deleteMultiple']);
        Gate::define('delete multiple customers', [CustomerPolicy::class, 'deleteMultiple']);
        Gate::define('delete multiple users', [UserPolicy::class, 'deleteMultiple']);
        Gate::define('delete multiple invoices', [InvoicePolicy::class, 'deleteMultiple']);
        Gate::define('delete multiple estimates', [EstimatePolicy::class, 'deleteMultiple']);
        Gate::define('delete multiple expenses', [ExpensePolicy::class, 'deleteMultiple']);
        Gate::define('delete multiple payments', [PaymentPolicy::class, 'deleteMultiple']);
        Gate::define('delete multiple recurring invoices', [RecurringInvoicePolicy::class, 'deleteMultiple']);

        Gate::define('view dashboard', [DashboardPolicy::class, 'view']);

        Gate::define('view report', [ReportPolicy::class, 'viewReport']);

        Gate::define('owner only', [OwnerPolicy::class, 'managedByOwner']);
    }

    public function bootBroadcast()
    {
        Broadcast::routes(['middleware' => 'api.auth']);
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // Rate limiter for invoice retrieval APIs (GET requests)
        RateLimiter::for('invoice-retrieval', function (Request $request) {
            $user = $request->user();
            
            // Super admins get unlimited access
            if ($user && $user->isOwner()) {
                return Limit::none();
            }
            
            // Admin users get higher limits
            if ($user && $user->hasRole('admin')) {
                return Limit::perMinute(120)->by($user->id);
            }
            
            // Regular users get standard limits
            if ($user) {
                return Limit::perMinute(60)->by($user->id);
            }
            
            // Guest users get lower limits
            return Limit::perMinute(30)->by($request->ip());
        });
        
        // Rate limiter for invoice actions (POST, PUT, DELETE requests)
        RateLimiter::for('invoice-actions', function (Request $request) {
            $user = $request->user();
            
            // Super admins get unlimited access
            if ($user && $user->isOwner()) {
                return Limit::none();
            }
            
            // Admin users get higher limits
            if ($user && $user->hasRole('admin')) {
                return Limit::perMinute(60)->by($user->id);
            }
            
            // Regular users get standard limits
            if ($user) {
                return Limit::perMinute(30)->by($user->id);
            }
            
            // No guest access for actions
            return Limit::perMinute(0);
        });
        
        // Rate limiter for customer invoice access
        RateLimiter::for('customer-invoices', function (Request $request) {
            $customer = $request->user('customer');
            
            if ($customer) {
                return Limit::perMinute(30)->by($customer->id);
            }
            
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
