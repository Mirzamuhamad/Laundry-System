<?php

namespace Tests\Feature;

use App\Livewire\AttendancePage;
use App\Livewire\ManagementPage;
use App\Livewire\PosPage;
use App\Livewire\ReportsPage;
use App\Livewire\TransactionsPage;
use App\Livewire\UserAccountsPage;
use App\Models\Attendance;
use App\Models\Category;
use App\Models\Customer;
use App\Models\EmployeeSchedule;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Outlet;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class LaundryFlowTest extends TestCase
{
    use RefreshDatabase;

    private function setupBusiness(): array
    {
        $outlet = Outlet::create(['name' => 'Outlet Test', 'code' => 'TST']);
        $owner = User::create(['name' => 'Owner', 'email' => 'owner@test.local', 'role' => 'owner', 'password' => 'password']);
        $cashier = User::create(['name' => 'Kasir', 'email' => 'cashier@test.local', 'role' => 'cashier', 'outlet_id' => $outlet->id, 'password' => 'password']);
        $category = Category::create(['name' => 'Kiloan']);
        $product = Product::create(['name' => 'Cuci Lipat', 'category_id' => $category->id, 'unit' => 'kg', 'price' => 8000, 'minimum_quantity' => 3, 'rounding_increment' => .5, 'duration_hours' => 48]);
        $customer = Customer::create(['name' => 'Budi', 'phone' => '08123456789', 'outlet_id' => $outlet->id]);

        return compact('outlet', 'owner', 'cashier', 'product', 'customer');
    }

    public function test_login_page_is_available_and_private_pages_require_login(): void
    {
        $this->get('/login')->assertOk();
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_cashier_cannot_open_owner_reports(): void
    {
        ['cashier' => $cashier] = $this->setupBusiness();
        $this->actingAs($cashier)->get('/reports')->assertForbidden();
    }

    public function test_mobile_navigation_renders_five_primary_items_and_groups_secondary_actions(): void
    {
        ['owner' => $owner, 'cashier' => $cashier] = $this->setupBusiness();

        $ownerResponse = $this->actingAs($owner)->get('/pos');
        $cashierResponse = $this->actingAs($cashier)->get('/pos');

        $ownerResponse
            ->assertSee('bottom-more-menu', false)
            ->assertSee('Absensi')
            ->assertSee('Laporan')
            ->assertSee('Akhiri sesi');
        $this->assertSame(5, substr_count($ownerResponse->getContent(), 'data-mobile-nav-item'));
        $this->assertSame(5, substr_count($cashierResponse->getContent(), 'data-mobile-nav-item'));
    }

    public function test_customer_picker_renders_immediately_when_opened(): void
    {
        ['cashier' => $cashier, 'customer' => $customer] = $this->setupBusiness();

        Livewire::actingAs($cashier)->test(PosPage::class)
            ->set('showCustomer', true)
            ->assertSet('showCustomer', true)
            ->assertSee('Cari nama atau WhatsApp...')
            ->assertSee($customer->name);
    }

    public function test_pos_header_renders_labeled_printer_and_outlet_controls(): void
    {
        ['owner' => $owner] = $this->setupBusiness();

        Livewire::actingAs($owner)->test(PosPage::class)
            ->assertSeeHtml('class="printer-control"')
            ->assertSee('Printer')
            ->assertSee('Outlet');
    }

    public function test_product_card_displays_its_quantity_after_being_added_to_the_cart(): void
    {
        ['cashier' => $cashier, 'product' => $product] = $this->setupBusiness();

        Livewire::actingAs($cashier)->test(PosPage::class)
            ->assertDontSeeHtml('class="product-cart-qty"')
            ->call('addProduct', $product->id)
            ->assertSeeHtml('class="product-cart-qty"')
            ->assertSee('3 kg')
            ->call('changeQuantity', (string) $product->id, 4.2)
            ->assertSee('4,5 kg')
            ->call('removeItem', (string) $product->id)
            ->assertDontSeeHtml('class="product-cart-qty"');
    }

    public function test_pos_creates_a_paid_order_with_historical_item_price(): void
    {
        ['outlet' => $outlet, 'cashier' => $cashier, 'product' => $product, 'customer' => $customer] = $this->setupBusiness();
        Livewire::actingAs($cashier)->test(PosPage::class)
            ->set('outletId', $outlet->id)
            ->call('addProduct', $product->id)
            ->set('customerId', $customer->id)
            ->set('paymentAmount', 24000)
            ->call('saveOrder', true)
            ->assertHasNoErrors()
            ->assertDispatched('print-receipt', function (string $event, array $parameters): bool {
                return str_contains($parameters['text'], '[QR]http://localhost')
                    && str_contains($parameters['text'], '/transactions/')
                    && str_contains($parameters['text'], '/receipt')
                    && ! str_contains($parameters['fallbackUrl'], 'autoprint');
            })
            ->assertSee('TRANSAKSI BERHASIL')
            ->assertSee($customer->name);
        $this->assertDatabaseHas('orders', ['customer_id' => $customer->id, 'total' => 24000, 'paid_amount' => 24000, 'payment_status' => 'paid']);
        $this->assertDatabaseHas('order_items', ['product_name' => 'Cuci Lipat', 'unit_price' => 8000, 'quantity' => 3]);
    }

    public function test_pos_cart_is_restored_after_leaving_and_returning_to_the_page(): void
    {
        ['cashier' => $cashier, 'product' => $product] = $this->setupBusiness();

        Livewire::actingAs($cashier)->test(PosPage::class)
            ->call('addProduct', $product->id)
            ->call('changeQuantity', (string) $product->id, 4.2);

        $product->update(['price' => 9000]);

        Livewire::actingAs($cashier)->test(PosPage::class)
            ->assertSet('cart.'.$product->id.'.quantity', 4.5)
            ->assertSet('cart.'.$product->id.'.price', 9000)
            ->assertSet('total', 40500);
    }

    public function test_pos_cart_session_is_cleared_after_an_order_is_saved(): void
    {
        ['cashier' => $cashier, 'product' => $product, 'customer' => $customer] = $this->setupBusiness();

        $component = Livewire::actingAs($cashier)->test(PosPage::class)
            ->call('addProduct', $product->id)
            ->set('customerId', $customer->id)
            ->set('paymentAmount', 24000)
            ->call('saveOrder', false)
            ->assertHasNoErrors()
            ->assertSet('cart', []);

        $order = Order::latest('id')->firstOrFail();
        $component
            ->assertSet('completedOrderId', $order->id)
            ->call('printCompletedOrder')
            ->assertDispatched('print-receipt', fn (string $event, array $parameters): bool => $parameters['fallbackUrl'] === route('transactions.receipt', $order))
            ->dispatch('receipt-print-finished')
            ->assertSet('completedOrderId', null);

        Livewire::actingAs($cashier)->test(PosPage::class)
            ->assertSet('cart', []);
    }

    public function test_receipt_contains_a_qr_link_to_the_allowed_transaction(): void
    {
        ['cashier' => $cashier, 'outlet' => $outlet, 'customer' => $customer] = $this->setupBusiness();
        $order = Order::create([
            'number' => 'TST-QR',
            'outlet_id' => $outlet->id,
            'customer_id' => $customer->id,
            'user_id' => $cashier->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'subtotal' => 10000,
            'total' => 10000,
            'paid_amount' => 0,
        ]);

        $this->actingAs($cashier)->get(route('transactions.receipt', $order))
            ->assertSee('Scan untuk membuka transaksi')
            ->assertSee(route('transactions.receipt', $order))
            ->assertSee('https://quickchart.io/qr?', false);
    }

    public function test_cashier_cannot_open_a_qr_transaction_from_another_outlet(): void
    {
        ['cashier' => $cashier, 'customer' => $customer] = $this->setupBusiness();
        $otherOutlet = Outlet::create(['name' => 'Outlet Lain', 'code' => 'OTH']);
        $otherCashier = User::create(['name' => 'Kasir Lain', 'email' => 'other@test.local', 'role' => 'cashier', 'outlet_id' => $otherOutlet->id, 'password' => 'password']);
        $order = Order::create([
            'number' => 'TST-OTHER',
            'outlet_id' => $otherOutlet->id,
            'customer_id' => $customer->id,
            'user_id' => $otherCashier->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'subtotal' => 10000,
            'total' => 10000,
            'paid_amount' => 0,
        ]);

        $this->actingAs($cashier)->get(route('transactions.receipt', $order))->assertForbidden();
    }

    public function test_transaction_print_action_dispatches_a_bluetooth_receipt_with_qr(): void
    {
        ['cashier' => $cashier, 'outlet' => $outlet, 'customer' => $customer] = $this->setupBusiness();
        $order = Order::create([
            'number' => 'TST-PRINT',
            'outlet_id' => $outlet->id,
            'customer_id' => $customer->id,
            'user_id' => $cashier->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'subtotal' => 10000,
            'total' => 10000,
            'paid_amount' => 0,
        ]);

        Livewire::actingAs($cashier)->test(TransactionsPage::class)
            ->call('printOrder', $order->id)
            ->assertDispatched('print-receipt', function (string $event, array $parameters) use ($order): bool {
                return str_contains($parameters['text'], '[QR]'.route('transactions.receipt', $order))
                    && $parameters['fallbackUrl'] === route('transactions.receipt', $order);
            });
    }

    public function test_employee_can_check_in_once_using_a_camera_photo(): void
    {
        Storage::fake('local');
        ['outlet' => $outlet, 'cashier' => $cashier] = $this->setupBusiness();
        EmployeeSchedule::create(['user_id' => $cashier->id, 'start_time' => '23:59', 'end_time' => '23:59', 'tolerance_minutes' => 0]);
        $photo = 'data:image/jpeg;base64,'.base64_encode('fake-jpeg-content');
        Livewire::actingAs($cashier)->test(AttendancePage::class)->set('outletId', $outlet->id)->call('checkIn', $photo)->assertHasNoErrors();
        Livewire::actingAs($cashier)->test(AttendancePage::class)->set('outletId', $outlet->id)->call('checkOut', $photo)->assertHasNoErrors();
        $this->assertDatabaseHas('attendances', ['user_id' => $cashier->id, 'outlet_id' => $outlet->id]);
        $this->assertNotNull($cashier->attendances()->first()->check_out_at);
    }

    public function test_check_out_without_check_in_returns_a_friendly_event(): void
    {
        Storage::fake('local');
        ['outlet' => $outlet, 'cashier' => $cashier] = $this->setupBusiness();
        $photo = 'data:image/jpeg;base64,'.base64_encode('fake-jpeg-content');
        Livewire::actingAs($cashier)->test(AttendancePage::class)->set('outletId', $outlet->id)->call('checkOut', $photo)->assertDispatched('attendance-failed');
        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_owner_dashboard_and_reports_render(): void
    {
        ['owner' => $owner] = $this->setupBusiness();
        $this->actingAs($owner)->get('/dashboard')->assertOk();
        $this->actingAs($owner)->get('/reports')->assertOk();
    }

    public function test_owner_can_see_the_outlet_list_in_management(): void
    {
        ['owner' => $owner] = $this->setupBusiness();
        Outlet::create([
            'name' => 'Outlet Nonaktif',
            'code' => 'OFF',
            'address' => 'Jl. Mawar No. 5',
            'phone' => '081200000000',
            'is_active' => false,
        ]);

        Livewire::actingAs($owner)->test(ManagementPage::class)
            ->assertSee('Daftar outlet')
            ->assertSee('2 lokasi operasional')
            ->assertSee('Outlet Test')
            ->assertSee('Outlet Nonaktif')
            ->assertSee('Jl. Mawar No. 5')
            ->assertSee('1 karyawan')
            ->assertSee('0 karyawan')
            ->assertSee('Nonaktif');
    }

    public function test_owner_can_update_an_outlet(): void
    {
        ['owner' => $owner, 'outlet' => $outlet] = $this->setupBusiness();

        Livewire::actingAs($owner)->test(ManagementPage::class)
            ->call('editOutlet', $outlet->id)
            ->set('outletName', 'Outlet Utama')
            ->set('outletCode', 'utm')
            ->set('outletAddress', 'Jl. Utama No. 1')
            ->set('outletPhone', '081211111111')
            ->set('outletIsActive', false)
            ->call('updateOutlet')
            ->assertHasNoErrors()
            ->assertSet('editingOutletId', null)
            ->assertDispatched('notify');

        $this->assertDatabaseHas('outlets', [
            'id' => $outlet->id,
            'name' => 'Outlet Utama',
            'code' => 'UTM',
            'address' => 'Jl. Utama No. 1',
            'phone' => '081211111111',
            'is_active' => false,
        ]);
    }

    public function test_owner_can_add_an_outlet(): void
    {
        ['owner' => $owner] = $this->setupBusiness();

        Livewire::actingAs($owner)->test(ManagementPage::class)
            ->set('outletName', 'Outlet Baru')
            ->set('outletCode', 'new')
            ->set('outletAddress', 'Jl. Baru No. 2')
            ->set('outletPhone', '081222222222')
            ->call('addOutlet')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $this->assertDatabaseHas('outlets', [
            'name' => 'Outlet Baru',
            'code' => 'NEW',
            'address' => 'Jl. Baru No. 2',
            'phone' => '081222222222',
        ]);
    }

    public function test_soft_deleting_an_outlet_deactivates_its_employees(): void
    {
        ['owner' => $owner, 'outlet' => $outlet, 'cashier' => $cashier] = $this->setupBusiness();

        Livewire::actingAs($owner)->test(ManagementPage::class)
            ->call('confirmDeleteOutlet', $outlet->id)
            ->assertSet('deletingOutletId', $outlet->id)
            ->call('deleteOutlet')
            ->assertSet('deletingOutletId', null)
            ->assertDispatched('notify');

        $this->assertSoftDeleted('outlets', ['id' => $outlet->id]);
        $this->assertDatabaseHas('users', ['id' => $cashier->id, 'is_active' => false]);
        $this->assertTrue($cashier->fresh()->outlet->is($outlet));
    }

    public function test_employee_cannot_be_assigned_to_a_deleted_outlet(): void
    {
        ['owner' => $owner, 'outlet' => $outlet] = $this->setupBusiness();
        $outlet->delete();

        Livewire::actingAs($owner)->test(ManagementPage::class)
            ->set('employeeName', 'Kasir Baru')
            ->set('employeeEmail', 'baru@test.local')
            ->set('employeePassword', 'password')
            ->set('employeeOutlet', $outlet->id)
            ->call('addEmployee')
            ->assertHasErrors(['employeeOutlet']);

        $this->assertDatabaseMissing('users', ['email' => 'baru@test.local']);
    }

    public function test_outlet_update_rejects_another_outlets_code(): void
    {
        ['owner' => $owner, 'outlet' => $outlet] = $this->setupBusiness();
        Outlet::create(['name' => 'Outlet Lain', 'code' => 'OTH']);

        Livewire::actingAs($owner)->test(ManagementPage::class)
            ->call('editOutlet', $outlet->id)
            ->set('outletCode', 'OTH')
            ->call('updateOutlet')
            ->assertHasErrors(['outletCode']);

        $this->assertDatabaseHas('outlets', ['id' => $outlet->id, 'code' => 'TST']);
    }

    public function test_cashier_cannot_open_outlet_management(): void
    {
        ['cashier' => $cashier] = $this->setupBusiness();

        $this->actingAs($cashier)->get('/management')->assertForbidden();
    }

    public function test_owner_can_view_employees_for_each_outlet(): void
    {
        ['owner' => $owner, 'outlet' => $outlet, 'cashier' => $cashier] = $this->setupBusiness();

        Livewire::actingAs($owner)->test(ManagementPage::class)
            ->call('viewOutletEmployees', $outlet->id)
            ->assertSet('selectedOutletId', $outlet->id)
            ->assertSee('Karyawan di Outlet Test')
            ->assertSee('1 karyawan di outlet ini')
            ->assertSee($cashier->name)
            ->assertSee($cashier->email);
    }

    public function test_owner_can_add_an_employee_with_an_assigned_outlet_and_schedule(): void
    {
        ['owner' => $owner, 'outlet' => $outlet] = $this->setupBusiness();

        Livewire::actingAs($owner)->test(ManagementPage::class)
            ->set('employeeName', 'Rina Kasir')
            ->set('employeeEmail', 'rina@test.local')
            ->set('employeePhone', '081233344455')
            ->set('employeePassword', 'password-baru')
            ->set('employeeOutlet', $outlet->id)
            ->set('startTime', '09:00')
            ->set('endTime', '18:00')
            ->set('tolerance', 15)
            ->call('addEmployee')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $employee = User::where('email', 'rina@test.local')->firstOrFail();
        $this->assertSame($outlet->id, $employee->outlet_id);
        $this->assertTrue(Hash::check('password-baru', $employee->password));
        $this->assertDatabaseHas('employee_schedules', [
            'user_id' => $employee->id,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'tolerance_minutes' => 15,
        ]);
    }

    public function test_owner_can_open_the_user_login_menu_and_create_a_cashier_account(): void
    {
        ['owner' => $owner, 'outlet' => $outlet] = $this->setupBusiness();

        $this->actingAs($owner)->get('/user-login')
            ->assertSee('User login')
            ->assertSee('Tambah user login')
            ->assertSee('Daftar user login');

        Livewire::actingAs($owner)->test(UserAccountsPage::class)
            ->set('accountName', 'Kasir Baru')
            ->set('accountEmail', 'kasir.baru@test.local')
            ->set('accountPhone', '08122223333')
            ->set('accountPassword', 'password-baru')
            ->set('accountRole', 'cashier')
            ->set('accountOutletId', $outlet->id)
            ->call('saveAccount')
            ->assertHasNoErrors()
            ->assertDispatched('notify');

        $account = User::where('email', 'kasir.baru@test.local')->firstOrFail();
        $this->assertSame('cashier', $account->role);
        $this->assertSame($outlet->id, $account->outlet_id);
        $this->assertTrue(Hash::check('password-baru', $account->password));
        $this->assertDatabaseHas('employee_schedules', [
            'user_id' => $account->id,
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);
    }

    public function test_cashier_account_requires_an_active_outlet(): void
    {
        ['owner' => $owner] = $this->setupBusiness();

        Livewire::actingAs($owner)->test(UserAccountsPage::class)
            ->set('accountName', 'Kasir Tanpa Outlet')
            ->set('accountEmail', 'tanpa.outlet@test.local')
            ->set('accountPassword', 'password-baru')
            ->set('accountRole', 'cashier')
            ->set('accountOutletId', null)
            ->call('saveAccount')
            ->assertHasErrors(['accountOutletId']);

        $this->assertDatabaseMissing('users', ['email' => 'tanpa.outlet@test.local']);
    }

    public function test_owner_can_edit_login_account_without_replacing_its_password(): void
    {
        ['owner' => $owner, 'cashier' => $cashier] = $this->setupBusiness();
        $oldPassword = $cashier->password;

        Livewire::actingAs($owner)->test(UserAccountsPage::class)
            ->call('editAccount', $cashier->id)
            ->assertSet('editingAccountId', $cashier->id)
            ->set('accountName', 'Supervisor')
            ->set('accountEmail', 'supervisor@test.local')
            ->set('accountPassword', '')
            ->set('accountRole', 'owner')
            ->set('accountIsActive', true)
            ->call('saveAccount')
            ->assertHasNoErrors()
            ->assertSet('editingAccountId', null)
            ->assertDispatched('notify');

        $cashier->refresh();
        $this->assertSame('Supervisor', $cashier->name);
        $this->assertSame('supervisor@test.local', $cashier->email);
        $this->assertSame('owner', $cashier->role);
        $this->assertNull($cashier->outlet_id);
        $this->assertSame($oldPassword, $cashier->password);
    }

    public function test_owner_can_disable_another_login_account(): void
    {
        ['owner' => $owner, 'cashier' => $cashier] = $this->setupBusiness();

        Livewire::actingAs($owner)->test(UserAccountsPage::class)
            ->call('toggleAccount', $cashier->id)
            ->assertDispatched('notify');

        $this->assertFalse($cashier->fresh()->is_active);
    }

    public function test_cashier_cannot_open_user_login_management(): void
    {
        ['cashier' => $cashier] = $this->setupBusiness();

        $this->actingAs($cashier)->get('/user-login')->assertForbidden();
    }

    public function test_owner_can_edit_an_employee_and_move_them_to_another_outlet(): void
    {
        ['owner' => $owner, 'cashier' => $cashier] = $this->setupBusiness();
        $newOutlet = Outlet::create(['name' => 'Outlet Baru', 'code' => 'NEW']);
        EmployeeSchedule::create(['user_id' => $cashier->id, 'start_time' => '08:00', 'end_time' => '17:00', 'tolerance_minutes' => 10]);
        $oldPassword = $cashier->password;

        Livewire::actingAs($owner)->test(ManagementPage::class)
            ->call('editEmployee', $cashier->id)
            ->assertSet('employeeOutlet', $cashier->outlet_id)
            ->set('employeeName', 'Kasir Diperbarui')
            ->set('employeeOutlet', $newOutlet->id)
            ->set('startTime', '10:00')
            ->set('endTime', '19:00')
            ->set('tolerance', 5)
            ->set('employeeIsActive', false)
            ->call('updateEmployee')
            ->assertHasNoErrors()
            ->assertSet('editingEmployeeId', null)
            ->assertDispatched('notify');

        $cashier->refresh();
        $this->assertSame('Kasir Diperbarui', $cashier->name);
        $this->assertSame($newOutlet->id, $cashier->outlet_id);
        $this->assertSame($oldPassword, $cashier->password);
        $this->assertFalse($cashier->is_active);
        $this->assertDatabaseHas('employee_schedules', [
            'user_id' => $cashier->id,
            'start_time' => '10:00',
            'end_time' => '19:00',
            'tolerance_minutes' => 5,
        ]);
    }

    public function test_owner_can_soft_delete_an_employee_while_preserving_history(): void
    {
        ['owner' => $owner, 'cashier' => $cashier, 'outlet' => $outlet, 'customer' => $customer] = $this->setupBusiness();
        $order = Order::create([
            'number' => 'TST-DELETE',
            'outlet_id' => $outlet->id,
            'customer_id' => $customer->id,
            'user_id' => $cashier->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'subtotal' => 10000,
            'total' => 10000,
            'paid_amount' => 0,
        ]);

        Livewire::actingAs($owner)->test(ManagementPage::class)
            ->call('confirmDeleteEmployee', $cashier->id)
            ->assertSet('deletingEmployeeId', $cashier->id)
            ->call('deleteEmployee')
            ->assertSet('deletingEmployeeId', null)
            ->assertDispatched('notify');

        $this->assertSoftDeleted('users', ['id' => $cashier->id]);
        $this->assertDatabaseHas('users', ['id' => $cashier->id, 'is_active' => false]);
        $this->assertTrue($order->fresh()->user->is($cashier));
        $this->post('/logout')->assertRedirect('/login');
        $this->post('/login', ['email' => $cashier->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
    }

    public function test_cashier_pos_is_locked_to_their_assigned_outlet(): void
    {
        ['cashier' => $cashier, 'outlet' => $outlet] = $this->setupBusiness();
        $otherOutlet = Outlet::create(['name' => 'Outlet Lain', 'code' => 'OTH']);

        Livewire::actingAs($cashier)->test(PosPage::class)
            ->set('outletId', $otherOutlet->id)
            ->assertSet('outletId', $outlet->id);
    }

    public function test_cashier_attendance_is_recorded_at_their_assigned_outlet(): void
    {
        Storage::fake('local');
        ['cashier' => $cashier, 'outlet' => $outlet] = $this->setupBusiness();
        $otherOutlet = Outlet::create(['name' => 'Outlet Lain', 'code' => 'OTH']);
        $photo = 'data:image/jpeg;base64,'.base64_encode('fake-jpeg-content');

        Livewire::actingAs($cashier)->test(AttendancePage::class)
            ->set('outletId', $otherOutlet->id)
            ->call('checkIn', $photo)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('attendances', [
            'user_id' => $cashier->id,
            'outlet_id' => $outlet->id,
        ]);
        $this->assertDatabaseMissing('attendances', [
            'user_id' => $cashier->id,
            'outlet_id' => $otherOutlet->id,
        ]);
    }

    public function test_attendance_history_includes_the_end_date_and_marks_late_employees(): void
    {
        ['owner' => $owner, 'cashier' => $cashier, 'outlet' => $outlet] = $this->setupBusiness();
        $attendance = Attendance::create([
            'user_id' => $cashier->id,
            'outlet_id' => $outlet->id,
            'attendance_date' => today()->toDateString(),
            'check_in_at' => today()->setTime(9, 5),
            'check_in_photo' => 'attendance/test/in.jpg',
            'check_out_at' => today()->setTime(17, 0),
            'check_out_photo' => 'attendance/test/out.jpg',
            'status' => 'late',
        ]);

        Livewire::actingAs($owner)->test(AttendancePage::class)
            ->set('outletId', $outlet->id)
            ->set('from', today()->toDateString())
            ->set('to', today()->toDateString())
            ->assertSee('Kasir')
            ->assertSee('Terlambat')
            ->assertSee('Foto masuk Kasir')
            ->assertSee('Foto keluar Kasir')
            ->assertSee(route('attendance.photo', [$attendance, 'in']), false)
            ->assertSee(route('attendance.photo', [$attendance, 'out']), false)
            ->assertSeeHtml('class="is-late"');
    }

    public function test_report_subtracts_end_date_expense_and_lists_attendance(): void
    {
        ['owner' => $owner, 'cashier' => $cashier, 'outlet' => $outlet, 'customer' => $customer] = $this->setupBusiness();
        $order = Order::create([
            'number' => 'TST-001',
            'outlet_id' => $outlet->id,
            'customer_id' => $customer->id,
            'user_id' => $cashier->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'subtotal' => 1000000,
            'total' => 1000000,
            'paid_amount' => 1000000,
            'payment_status' => 'paid',
        ]);
        Payment::create(['order_id' => $order->id, 'user_id' => $cashier->id, 'method' => 'cash', 'amount' => 1000000, 'paid_at' => now()]);
        Expense::create(['outlet_id' => $outlet->id, 'user_id' => $owner->id, 'expense_date' => today()->toDateString(), 'category' => 'Operasional', 'amount' => 200000]);
        Attendance::create(['user_id' => $cashier->id, 'outlet_id' => $outlet->id, 'attendance_date' => today()->toDateString(), 'check_in_at' => now(), 'status' => 'late']);

        Livewire::actingAs($owner)->test(ReportsPage::class)
            ->set('from', today()->toDateString())
            ->set('to', today()->toDateString())
            ->assertSee('Rp200.000')
            ->assertSee('Rp800.000')
            ->assertSee('Laporan absensi')
            ->assertSee('Kasir')
            ->assertSee('Terlambat');
    }

    public function test_report_tables_have_independent_per_page_controls_and_paginators(): void
    {
        ['owner' => $owner, 'cashier' => $cashier, 'outlet' => $outlet, 'customer' => $customer] = $this->setupBusiness();

        foreach (range(1, 11) as $index) {
            Order::create([
                'number' => 'PAGE-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'outlet_id' => $outlet->id,
                'customer_id' => $customer->id,
                'user_id' => $cashier->id,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'subtotal' => 10000,
                'total' => 10000,
                'paid_amount' => 0,
            ]);
            Expense::create([
                'outlet_id' => $outlet->id,
                'user_id' => $owner->id,
                'expense_date' => today()->toDateString(),
                'category' => 'Pagination '.$index,
                'amount' => 1000,
            ]);
            Attendance::create([
                'user_id' => $cashier->id,
                'outlet_id' => $outlet->id,
                'attendance_date' => today()->subDays($index - 1)->toDateString(),
                'check_in_at' => now()->subDays($index - 1),
                'status' => 'present',
            ]);
        }

        Livewire::actingAs($owner)->test(ReportsPage::class)
            ->set('from', today()->subDays(20)->toDateString())
            ->assertViewHas('reportOrders', fn ($paginator): bool => $paginator->total() === 11 && $paginator->count() === 10)
            ->assertViewHas('reportExpenses', fn ($paginator): bool => $paginator->total() === 11 && $paginator->count() === 10)
            ->assertViewHas('reportAttendances', fn ($paginator): bool => $paginator->total() === 11 && $paginator->count() === 10)
            ->call('setPage', 2, 'ordersPage')
            ->assertSet('paginators.ordersPage', 2)
            ->set('ordersPerPage', 25)
            ->assertSet('paginators.ordersPage', 1)
            ->assertViewHas('reportOrders', fn ($paginator): bool => $paginator->count() === 11)
            ->assertViewHas('reportExpenses', fn ($paginator): bool => $paginator->count() === 10)
            ->set('expensesPerPage', 25)
            ->assertViewHas('reportExpenses', fn ($paginator): bool => $paginator->count() === 11)
            ->set('attendancesPerPage', 25)
            ->assertViewHas('reportAttendances', fn ($paginator): bool => $paginator->count() === 11);
    }

    public function test_transaction_list_can_change_the_number_of_records_per_page(): void
    {
        ['owner' => $owner, 'cashier' => $cashier, 'outlet' => $outlet, 'customer' => $customer] = $this->setupBusiness();

        foreach (range(1, 11) as $index) {
            Order::create([
                'number' => 'LIST-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT),
                'outlet_id' => $outlet->id,
                'customer_id' => $customer->id,
                'user_id' => $cashier->id,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'subtotal' => 10000,
                'total' => 10000,
                'paid_amount' => 0,
            ]);
        }

        Livewire::actingAs($owner)->test(TransactionsPage::class)
            ->assertViewHas('orders', fn ($paginator): bool => $paginator->total() === 11 && $paginator->count() === 10)
            ->assertSee('Filter & pencarian', false)
            ->assertSee('Total transaksi')
            ->assertSee('Cetak struk')
            ->call('setPage', 2)
            ->assertSet('paginators.page', 2)
            ->set('perPage', 25)
            ->assertSet('paginators.page', 1)
            ->assertViewHas('orders', fn ($paginator): bool => $paginator->count() === 11)
            ->set('perPage', 999)
            ->assertSet('perPage', 10);
    }

    public function test_transaction_list_can_show_unfinished_orders_due_today(): void
    {
        ['owner' => $owner, 'cashier' => $cashier, 'outlet' => $outlet, 'customer' => $customer] = $this->setupBusiness();
        foreach ([
            ['number' => 'DUE-TODAY', 'due_at' => today()->setTime(17, 0), 'status' => 'processing'],
            ['number' => 'DUE-TOMORROW', 'due_at' => today()->addDay()->setTime(17, 0), 'status' => 'processing'],
            ['number' => 'READY-TODAY', 'due_at' => today()->setTime(12, 0), 'status' => 'ready'],
        ] as $orderData) {
            Order::create([
                ...$orderData,
                'outlet_id' => $outlet->id,
                'customer_id' => $customer->id,
                'user_id' => $cashier->id,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'subtotal' => 10000,
                'total' => 10000,
                'paid_amount' => 0,
            ]);
        }

        Livewire::actingAs($owner)->test(TransactionsPage::class)
            ->assertViewHas('dueTodayCount', 1)
            ->set('dueTodayOnly', true)
            ->assertSet('paginators.page', 1)
            ->assertViewHas('orders', fn ($orders): bool => $orders->total() === 1)
            ->assertSee('DUE-TODAY')
            ->assertDontSee('DUE-TOMORROW')
            ->assertDontSee('READY-TODAY');
    }

    public function test_transaction_mobile_card_shows_expandable_service_details(): void
    {
        ['owner' => $owner, 'cashier' => $cashier, 'outlet' => $outlet, 'product' => $product, 'customer' => $customer] = $this->setupBusiness();
        $order = Order::create([
            'number' => 'MOBILE-SERVICE',
            'outlet_id' => $outlet->id,
            'customer_id' => $customer->id,
            'user_id' => $cashier->id,
            'customer_name' => $customer->name,
            'customer_phone' => $customer->phone,
            'subtotal' => 16000,
            'total' => 16000,
            'paid_amount' => 0,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_name' => 'Cuci Kering Lipat',
            'variant_name' => 'Reguler',
            'duration_hours' => 72,
            'unit' => 'kg',
            'quantity' => 2,
            'unit_price' => 8000,
            'subtotal' => 16000,
        ]);

        Livewire::actingAs($owner)->test(TransactionsPage::class)
            ->assertViewHas('orders', fn ($orders): bool => $orders->first()->relationLoaded('items'))
            ->assertSee('1 layanan · ketuk untuk melihat rincian')
            ->assertSee('1 jenis layanan')
            ->assertSee('Cuci Kering Lipat')
            ->assertSee('Reguler')
            ->assertSee('Kirim struk')
            ->assertSee('Rp16.000');
    }

    public function test_logout_uses_the_styled_confirmation_dialog(): void
    {
        ['owner' => $owner] = $this->setupBusiness();

        $this->actingAs($owner)->get('/dashboard')
            ->assertSee('Yakin ingin keluar?')
            ->assertSee('Ya, keluar')
            ->assertDontSee('return confirm', false);
    }

    public function test_owner_can_export_an_excel_report(): void
    {
        ['owner' => $owner] = $this->setupBusiness();
        $response = $this->actingAs($owner)->get('/reports/export?from='.today()->format('Y-m-01').'&to='.today()->format('Y-m-d'));
        $response->assertOk()->assertDownload();
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $this->assertNotFalse($zip->locateName('xl/worksheets/sheet1.xml'));
        $this->assertNotFalse($zip->locateName('xl/worksheets/sheet2.xml'));
        $this->assertNotFalse($zip->locateName('xl/worksheets/sheet3.xml'));
        $this->assertNotFalse($zip->locateName('xl/worksheets/sheet4.xml'));
        $zip->close();
    }
}
