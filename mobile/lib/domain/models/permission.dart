/// What a user may do, as granted by their role on the server.
///
/// Mirrors the backend's `App\Enums\Permission`; keys the app does not know
/// yet are dropped when parsing.
enum Permission {
  allOrganizations('data.all_organizations'),
  vehiclesView('vehicles.view'),
  vehiclesCreate('vehicles.create'),
  vehiclesUpdate('vehicles.update'),
  vehiclesDelete('vehicles.delete'),
  driversView('drivers.view'),
  driversCreate('drivers.create'),
  driversUpdate('drivers.update'),
  driversDelete('drivers.delete'),
  waybillsView('waybills.view'),
  waybillsCreate('waybills.create'),
  waybillsUpdate('waybills.update'),
  waybillsDelete('waybills.delete'),
  waybillsSubmit('waybills.submit'),
  waybillsMechanicCheck('waybills.mechanic_check'),
  waybillsDoctorCheck('waybills.doctor_check'),
  waybillsApprove('waybills.approve'),
  waybillsDepart('waybills.depart'),
  waybillsComplete('waybills.complete'),
  fuelView('fuel.view'),
  fuelStationsManage('fuel.stations.manage'),
  fuelTransactionsCreate('fuel.transactions.create'),
  fuelNormsView('fuel_norms.view'),
  fuelNormsManage('fuel_norms.manage'),
  reportsView('reports.view'),
  organizationsManage('organizations.manage'),
  usersView('users.view'),
  usersManage('users.manage');

  const Permission(this.apiValue);

  final String apiValue;

  static Set<Permission> setFromApi(Object? value) {
    if (value is! List) return const {};
    final keys = value.whereType<String>().toSet();
    return {
      for (final permission in values)
        if (keys.contains(permission.apiValue)) permission,
    };
  }
}
