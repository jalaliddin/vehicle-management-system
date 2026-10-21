<?php

namespace App\Enums;

/**
 * Every action the application can authorize.
 *
 * Roles are stored in the database, but the permissions they can hold are
 * defined here because only code that checks a permission gives it meaning.
 */
enum Permission: string
{
    case AllOrganizations = 'data.all_organizations';

    case VehiclesView = 'vehicles.view';
    case VehiclesCreate = 'vehicles.create';
    case VehiclesUpdate = 'vehicles.update';
    case VehiclesDelete = 'vehicles.delete';

    case DriversView = 'drivers.view';
    case DriversCreate = 'drivers.create';
    case DriversUpdate = 'drivers.update';
    case DriversDelete = 'drivers.delete';

    case WaybillsView = 'waybills.view';
    case WaybillsCreate = 'waybills.create';
    case WaybillsUpdate = 'waybills.update';
    case WaybillsDelete = 'waybills.delete';
    case WaybillsSubmit = 'waybills.submit';
    case WaybillsMechanicCheck = 'waybills.mechanic_check';
    case WaybillsDoctorCheck = 'waybills.doctor_check';
    case WaybillsApprove = 'waybills.approve';
    case WaybillsDepart = 'waybills.depart';
    case WaybillsComplete = 'waybills.complete';

    case FuelView = 'fuel.view';
    case FuelStationsManage = 'fuel.stations.manage';
    case FuelTransactionsCreate = 'fuel.transactions.create';
    case FuelNormsView = 'fuel_norms.view';
    case FuelNormsManage = 'fuel_norms.manage';

    case ReportsView = 'reports.view';

    case OrganizationsManage = 'organizations.manage';
    case UsersView = 'users.view';
    case UsersManage = 'users.manage';
    case AuditView = 'audit.view';

    case EmployeesView = 'employees.view';
    case EmployeesManage = 'employees.manage';
    case OrgStructureManage = 'org_structure.manage';

    case OhsView = 'ohs.view';
    case OhsMedicalView = 'ohs.medical.view';
    case OhsMedicalManage = 'ohs.medical.manage';
    case OhsBriefingsManage = 'ohs.briefings.manage';
    case OhsPpeManage = 'ohs.ppe.manage';

    public function label(): string
    {
        return match ($this) {
            self::AllOrganizations => "Barcha tashkilotlar ma'lumotlari",
            self::VehiclesView => "Avtomobillarni ko'rish",
            self::VehiclesCreate => "Avtomobil qo'shish",
            self::VehiclesUpdate => 'Avtomobilni tahrirlash',
            self::VehiclesDelete => "Avtomobilni o'chirish",
            self::DriversView => "Haydovchilarni ko'rish",
            self::DriversCreate => "Haydovchi qo'shish",
            self::DriversUpdate => 'Haydovchini tahrirlash',
            self::DriversDelete => "Haydovchini o'chirish",
            self::WaybillsView => "Yo'l varaqalarini ko'rish",
            self::WaybillsCreate => "Yo'l varaqasi yaratish",
            self::WaybillsUpdate => "Yo'l varaqasini tahrirlash",
            self::WaybillsDelete => "Yo'l varaqasini o'chirish",
            self::WaybillsSubmit => 'Mexanikka yuborish',
            self::WaybillsMechanicCheck => 'Mexanik tekshiruvi',
            self::WaybillsDoctorCheck => 'Shifokor tekshiruvi',
            self::WaybillsApprove => 'Tasdiqlash',
            self::WaybillsDepart => "Yo'lga chiqarish",
            self::WaybillsComplete => 'Safarni yakunlash',
            self::FuelView => "Yoqilg'i ma'lumotlarini ko'rish",
            self::FuelStationsManage => 'Shahobchalarni boshqarish',
            self::FuelTransactionsCreate => "Yoqilg'i harakati qo'shish",
            self::FuelNormsView => "Yoqilg'i me'yorlarini ko'rish",
            self::FuelNormsManage => "Yoqilg'i me'yorlarini boshqarish",
            self::ReportsView => "Hisobotlarni ko'rish",
            self::OrganizationsManage => 'Tashkilotlarni boshqarish',
            self::UsersView => "Foydalanuvchilarni ko'rish",
            self::UsersManage => 'Foydalanuvchilarni boshqarish',
            self::AuditView => "Amallar jurnalini ko'rish",
            self::EmployeesView => "Xodimlarni ko'rish",
            self::EmployeesManage => 'Xodimlarni boshqarish',
            self::OrgStructureManage => 'Tuzilma va lavozimlarni boshqarish',
            self::OhsView => "Mehnat muhofazasini ko'rish",
            self::OhsMedicalView => "Tibbiy ko'rik natijalarini ko'rish",
            self::OhsMedicalManage => "Tibbiy ko'riklarni boshqarish",
            self::OhsBriefingsManage => "Yo'riqnomalarni boshqarish",
            self::OhsPpeManage => 'Maxsus kiyim va SHHVni boshqarish',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::AllOrganizations => 'data',
            self::VehiclesView, self::VehiclesCreate, self::VehiclesUpdate, self::VehiclesDelete => 'vehicles',
            self::DriversView, self::DriversCreate, self::DriversUpdate, self::DriversDelete => 'drivers',
            self::WaybillsView, self::WaybillsCreate, self::WaybillsUpdate, self::WaybillsDelete,
            self::WaybillsSubmit, self::WaybillsMechanicCheck, self::WaybillsDoctorCheck,
            self::WaybillsApprove, self::WaybillsDepart, self::WaybillsComplete => 'waybills',
            self::FuelView, self::FuelStationsManage, self::FuelTransactionsCreate,
            self::FuelNormsView, self::FuelNormsManage => 'fuel',
            self::ReportsView => 'reports',
            self::EmployeesView, self::EmployeesManage, self::OrgStructureManage => 'hr',
            self::OhsView, self::OhsMedicalView, self::OhsMedicalManage,
            self::OhsBriefingsManage, self::OhsPpeManage => 'ohs',
            self::OrganizationsManage, self::UsersView, self::UsersManage, self::AuditView => 'admin',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function groupLabels(): array
    {
        return [
            'data' => "Ma'lumotlar doirasi",
            'vehicles' => 'Avtomobillar',
            'drivers' => 'Haydovchilar',
            'waybills' => "Yo'l varaqalari",
            'fuel' => "Yoqilg'i",
            'reports' => 'Hisobotlar',
            'hr' => 'Kadrlar',
            'ohs' => 'Mehnat muhofazasi',
            'admin' => 'Boshqaruv',
        ];
    }

    /**
     * The catalog in display order, for the role editor.
     *
     * @return list<array{key: string, label: string, permissions: list<array{key: string, label: string}>}>
     */
    public static function grouped(): array
    {
        return collect(self::groupLabels())
            ->map(fn (string $label, string $group) => [
                'key' => $group,
                'label' => $label,
                'permissions' => collect(self::cases())
                    ->filter(fn (self $permission) => $permission->group() === $group)
                    ->map(fn (self $permission) => ['key' => $permission->value, 'label' => $permission->label()])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
