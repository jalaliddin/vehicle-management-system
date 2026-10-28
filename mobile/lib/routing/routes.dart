abstract final class Routes {
  static const login = '/login';
  static const tasks = '/tasks';
  static const waybills = '/waybills';
  static const newWaybill = '/waybills/new';
  static const scanner = '/scan';
  static const profile = '/profile';
  static const fuel = '/fuel';
  static const fuelRefuel = '/fuel/refuel';
  static const fuelReceipt = '/fuel/receipt';
  static const fuelScan = '/fuel/scan';

  static String waybill(int id) => '/waybills/$id';
  static String fuelShift(int id) => '/fuel/shifts/$id';
}
