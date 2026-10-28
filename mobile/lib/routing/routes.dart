abstract final class Routes {
  static const login = '/login';
  static const tasks = '/tasks';
  static const waybills = '/waybills';
  static const newWaybill = '/waybills/new';
  static const scanner = '/scan';
  static const profile = '/profile';

  static String waybill(int id) => '/waybills/$id';
}
