// Renders the Play Store phone screenshots from the real app (router,
// screens, view models) with sample data, using Roboto and Material Icons
// from the Flutter SDK so text and icons look as on a device.
//
//   flutter test tool/store_screenshots_test.dart
//
// Output: store/screenshots/*.png, 1080x1920.
import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:mocktail/mocktail.dart';
import 'package:provider/provider.dart';
import 'package:utg_transport/data/repositories/auth_repository.dart';
import 'package:utg_transport/data/repositories/dashboard_repository.dart';
import 'package:utg_transport/data/repositories/reference_repository.dart';
import 'package:utg_transport/data/repositories/waybill_repository.dart';
import 'package:utg_transport/domain/models/dashboard_stats.dart';
import 'package:utg_transport/domain/models/driver.dart';
import 'package:utg_transport/domain/models/organization.dart';
import 'package:utg_transport/domain/models/permission.dart';
import 'package:utg_transport/domain/models/user.dart';
import 'package:utg_transport/domain/models/vehicle.dart';
import 'package:utg_transport/domain/models/waybill.dart';
import 'package:utg_transport/main.dart';
import 'package:utg_transport/routing/router.dart';
import 'package:utg_transport/routing/routes.dart';
import 'package:utg_transport/ui/features/waybills/view_models/waybill_create_view_model.dart';
import 'package:utg_transport/ui/features/waybills/views/waybill_create_screen.dart';

import '../test/helpers/fixtures.dart';

const _physicalSize = Size(1080, 1920);
const _pixelRatio = 2.625;
const _outputDir = 'store/screenshots';
final _screenKey = GlobalKey();

// --- Sample data -----------------------------------------------------------

const _urganch = Organization(
  id: 5,
  name: 'Urganch MGQB',
  shortName: 'Urganch',
);
const _xojayli = Organization(
  id: 6,
  name: "Xo'jayli sanoat maydonchasi",
  shortName: "Xo'jayli",
);

const _vehicles = [
  Vehicle(
    id: 1,
    stateNumber: '90 A 315 AB',
    model: 'Damas',
    organizationId: 5,
    vehicleType: VehicleType.car,
    fuelType: 'gaz',
    fuelNorm: 9,
  ),
  Vehicle(
    id: 2,
    stateNumber: '90 B 027 CA',
    model: 'KamAZ 43118',
    organizationId: 5,
    vehicleType: VehicleType.truck,
    fuelType: 'dizel',
    fuelNorm: 32,
    fuelNormAc: 34.5,
  ),
  Vehicle(
    id: 3,
    stateNumber: '90 C 741 BA',
    model: 'Isuzu NPR',
    organizationId: 5,
    vehicleType: VehicleType.truck,
    fuelType: 'dizel',
    fuelNorm: 16,
  ),
  Vehicle(
    id: 4,
    stateNumber: '90 D 552 AA',
    model: 'Cobalt',
    organizationId: 6,
    vehicleType: VehicleType.car,
    fuelType: 'benzin',
    fuelNorm: 8.5,
    fuelNormAc: 9.4,
  ),
];

const _drivers = [
  Driver(
    id: 1,
    fullName: 'Raximov Sanjar',
    organizationId: 5,
    licenseCategory: 'B, C',
  ),
  Driver(
    id: 2,
    fullName: 'Yusupov Bekzod',
    organizationId: 5,
    licenseCategory: 'C',
  ),
  Driver(
    id: 3,
    fullName: 'Matyoqubov Otabek',
    organizationId: 5,
    licenseCategory: 'B, C, D',
  ),
  Driver(
    id: 4,
    fullName: "Sapayev Ulug'bek",
    organizationId: 6,
    licenseCategory: 'B',
  ),
];

final _today = DateTime(2026, 10, 21);

Waybill _waybill(
  int id,
  WaybillStatus status, {
  int vehicle = 0,
  String destination = 'Urganch — Xiva GTS',
  Organization organization = _urganch,
  DateTime? mechanicAt,
  DateTime? doctorAt,
  DateTime? approvedAt,
  DateTime? departedAt,
  DateTime? arrivedAt,
  double? actualKm,
  double? fuelConsumed,
}) => Waybill(
  id: id,
  number: 'UTG-202610-${id.toString().padLeft(4, '0')}',
  status: status,
  organization: organization,
  vehicle: _vehicles[vehicle],
  driver: _drivers[vehicle],
  vehicleType: _vehicles[vehicle].vehicleType,
  tabelNumber: 'T-${1040 + id}',
  createdDate: _today,
  validUntil: _today.add(const Duration(days: 1)),
  destination: destination,
  destinationOrganization: 'Xiva gaz taqsimlash shoxobchasi',
  plannedKm: 124,
  actualKm: actualKm,
  fuelIssued: 40,
  fuelConsumed: fuelConsumed,
  hasAc: vehicle == 1,
  mechanicName: mechanicAt == null ? null : 'Karimov Jasur',
  mechanicCheckedAt: mechanicAt,
  mechanicPassed: mechanicAt == null ? null : true,
  mechanicNotes: mechanicAt == null
      ? null
      : "Tormoz va shinalar soz, moy almashtirilgan.",
  doctorName: doctorAt == null ? null : 'Dr. Yusupova Dilnoza',
  doctorCheckedAt: doctorAt,
  doctorPassed: doctorAt == null ? null : true,
  doctorNotes: doctorAt == null ? null : "Haydovchi ishga yaroqli.",
  approvedByName: approvedAt == null ? null : 'Allaberganov Rustam',
  approvedAt: approvedAt,
  departedAt: departedAt,
  arrivedAt: arrivedAt,
);

DateTime _at(int hour, int minute) =>
    _today.add(Duration(hours: hour, minutes: minute));

final _mechanicQueue = [
  _waybill(
    31,
    WaybillStatus.mechanicCheck,
    vehicle: 1,
    destination: "Bog'ot magistral liniyasi, 42-km",
  ),
  _waybill(32, WaybillStatus.mechanicCheck, vehicle: 0),
  _waybill(
    33,
    WaybillStatus.mechanicCheck,
    vehicle: 2,
    destination: 'Xonqa tumani, GRP-14',
  ),
];

final _awaitingApproval = [
  _waybill(
    27,
    WaybillStatus.doctorOk,
    vehicle: 1,
    destination: "Bog'ot magistral liniyasi, 42-km",
    mechanicAt: _at(7, 40),
    doctorAt: _at(7, 55),
  ),
  _waybill(
    28,
    WaybillStatus.doctorOk,
    vehicle: 3,
    organization: _xojayli,
    destination: "Xo'jayli — Nukus",
    mechanicAt: _at(7, 45),
    doctorAt: _at(8, 2),
  ),
];

final _onTheRoad = [
  _waybill(
    21,
    WaybillStatus.inProgress,
    vehicle: 2,
    destination: 'Shovot AGTKSh',
    mechanicAt: _at(7, 5),
    doctorAt: _at(7, 15),
    approvedAt: _at(7, 30),
    departedAt: _at(7, 50),
  ),
];

final _completed = _waybill(
  18,
  WaybillStatus.completed,
  vehicle: 1,
  destination: "Bog'ot magistral liniyasi, 42-km",
  mechanicAt: _at(7, 10),
  doctorAt: _at(7, 22),
  approvedAt: _at(7, 35),
  departedAt: _at(7, 58),
  arrivedAt: _at(16, 40),
  actualKm: 131.5,
  fuelConsumed: 43.2,
);

final _stats = DashboardStats(
  totalVehicles: 186,
  activeVehicles: 164,
  brokenVehicles: 9,
  maintenanceVehicles: 13,
  totalDrivers: 203,
  activeDrivers: 191,
  todayWaybills: 47,
  pendingWaybills: 6,
  activeWaybills: 18,
  fuelAlerts: [
    FuelStation.fromJson({
      'id': 1,
      'name': 'AGTKSh-2',
      'fuel_type': 'gaz',
      'current_balance': '420.00',
      'min_threshold': '1000.00',
      'capacity': '12000.00',
      'organization': {
        'id': 6,
        'name': "Xo'jayli sanoat maydonchasi",
        'short_name': "Xo'jayli",
      },
    }),
  ],
  activeTrips: _onTheRoad,
);

const _roleNames = {
  'hq_dispatcher': 'Markaziy dispetcher',
  'dispatcher': 'Dispetcher',
  'mechanic': 'Mexanik',
};

User _user(String role, String name, {Organization? organization = _urganch}) =>
    User(
      id: 1,
      name: name,
      email: '$role@utg.uz',
      roleSlug: role,
      roleName: _roleNames[role]!,
      permissions: Permission.setFromApi(systemRolePermissions[role]),
      organizationId: organization?.id,
      organization: organization,
    );

// --- Harness ---------------------------------------------------------------

class _App {
  _App(this.user) {
    when(() => auth.currentUser).thenReturn(user);
    when(() => auth.isAuthenticated).thenReturn(user != null);
    when(() => dashboard.stats()).thenAnswer((_) async => _stats);
    when(
      () => waybills.list(
        page: any(named: 'page'),
        perPage: any(named: 'perPage'),
        status: any(named: 'status'),
        search: any(named: 'search'),
      ),
    ).thenAnswer((invocation) async {
      final status = invocation.namedArguments[#status] as WaybillStatus?;
      final all = [
        ..._awaitingApproval,
        ..._mechanicQueue,
        ..._onTheRoad,
        _completed,
      ];
      return pageOf(
        status == null ? all : all.where((w) => w.status == status).toList(),
      );
    });
    when(() => references.activeVehicles()).thenAnswer((_) async => _vehicles);
    when(() => references.activeDrivers()).thenAnswer((_) async => _drivers);
    when(() => references.organizations())
        .thenAnswer((_) async => const [_urganch, _xojayli]);
  }

  final User? user;
  final auth = MockAuthRepository();
  final waybills = MockWaybillRepository();
  final dashboard = MockDashboardRepository();
  final references = MockReferenceRepository();
  late final GoRouter router = createRouter(auth);

  Widget build() => RepaintBoundary(
    key: _screenKey,
    child: MultiProvider(
      providers: [
        ChangeNotifierProvider<AuthRepository>.value(value: auth),
        ChangeNotifierProvider<WaybillRepository>.value(value: waybills),
        Provider<DashboardRepository>.value(value: dashboard),
        Provider<ReferenceRepository>.value(value: references),
      ],
      child: UtgTransportApp(router: router),
    ),
  );
}

Future<void> _loadFonts() async {
  // flutter_tester lives in <sdk>/bin/cache/artifacts/engine/<platform>/.
  final artifacts = File(Platform.resolvedExecutable).parent.parent.parent;
  final fonts = '${artifacts.path}/material_fonts';
  Future<ByteData> read(String file) async =>
      ByteData.sublistView(File('$fonts/$file').readAsBytesSync());

  final roboto = FontLoader('Roboto');
  for (final weight in ['Light', 'Regular', 'Medium', 'Bold']) {
    roboto.addFont(read('Roboto-$weight.ttf'));
  }
  await roboto.load();
  await (FontLoader(
    'MaterialIcons',
  )..addFont(read('MaterialIcons-Regular.otf'))).load();
}

Future<_App> _pumpApp(
  WidgetTester tester,
  User? user, {
  String? location,
}) async {
  tester.view
    ..physicalSize = _physicalSize
    ..devicePixelRatio = _pixelRatio
    ..padding = const FakeViewPadding(top: 24 * _pixelRatio)
    ..viewPadding = const FakeViewPadding(top: 24 * _pixelRatio);
  addTearDown(tester.view.reset);

  final app = _App(user);
  await tester.pumpWidget(app.build());
  await tester.pumpAndSettle();
  if (location != null) {
    app.router.push(location);
    await tester.pumpAndSettle();
  }
  return app;
}

Future<void> _save(WidgetTester tester, String name) async {
  FocusManager.instance.primaryFocus?.unfocus();
  await tester.pumpAndSettle();
  final boundary = tester.renderObject<RenderRepaintBoundary>(
    find.byKey(_screenKey),
  );
  await tester.runAsync(() async {
    final image = await boundary.toImage(pixelRatio: _pixelRatio);
    final png = await image.toByteData(format: ui.ImageByteFormat.png);
    File('$_outputDir/$name.png')
      ..createSync(recursive: true)
      ..writeAsBytesSync(png!.buffer.asUint8List());
  });
}

/// Tests draw elevation as solid black unless real shadows are on; the flag
/// must be restored inside the test body.
void _screenshot(String description, Future<void> Function(WidgetTester) body) {
  testWidgets(description, (tester) async {
    debugDisableShadows = false;
    try {
      await body(tester);
    } finally {
      debugDisableShadows = true;
    }
  });
}

// --- Screens -----------------------------------------------------------------

void main() {
  setUpAll(() async {
    registerFallbacks();
    await _loadFonts();
  });

  _screenshot('01 login', (tester) async {
    await _pumpApp(tester, null);
    await tester.runAsync(
      () => precacheImage(
        const AssetImage('assets/logo.png'),
        tester.element(find.byType(Scaffold)),
      ),
    );
    await tester.enterText(
      find.widgetWithText(TextField, 'Email'),
      'mexanik@utg.uz',
    );
    await _save(tester, '01_login');
  });

  _screenshot('02 mechanic queue', (tester) async {
    await _pumpApp(tester, _user('mechanic', 'Karimov Jasur'));
    await _save(tester, '02_mechanic_queue');
  });

  _screenshot('03 inspection', (tester) async {
    await _pumpApp(tester, _user('mechanic', 'Karimov Jasur'));
    await tester.tap(find.widgetWithText(FilledButton, 'Tasdiqlash').first);
    await tester.pumpAndSettle();
    await tester.enterText(
      find.descendant(
        of: find.byType(BottomSheet),
        matching: find.byType(TextField),
      ),
      "Tormoz va shinalar soz, moy almashtirilgan.",
    );
    await _save(tester, '03_inspection');
  });

  _screenshot('04 hq dashboard', (tester) async {
    await _pumpApp(
      tester,
      _user('hq_dispatcher', 'Allaberganov Rustam', organization: null),
    );
    await _save(tester, '04_hq_dashboard');
  });

  _screenshot('05 approval', (tester) async {
    final app = _App(
      _user('hq_dispatcher', 'Allaberganov Rustam', organization: null),
    );
    when(() => app.waybills.get(27))
        .thenAnswer((_) async => _awaitingApproval.first);
    tester.view
      ..physicalSize = _physicalSize
      ..devicePixelRatio = _pixelRatio
      ..padding = const FakeViewPadding(top: 24 * _pixelRatio);
    addTearDown(tester.view.reset);
    await tester.pumpWidget(app.build());
    await tester.pumpAndSettle();
    app.router.push(Routes.waybill(27));
    await tester.pumpAndSettle();
    await _save(tester, '05_approval');
  });

  _screenshot('06 timeline', (tester) async {
    final app = _App(_user('dispatcher', 'Rahimova Nodira'));
    when(() => app.waybills.get(18)).thenAnswer((_) async => _completed);
    tester.view
      ..physicalSize = _physicalSize
      ..devicePixelRatio = _pixelRatio
      ..padding = const FakeViewPadding(top: 24 * _pixelRatio);
    addTearDown(tester.view.reset);
    await tester.pumpWidget(app.build());
    await tester.pumpAndSettle();
    app.router.push(Routes.waybill(18));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(find.text('Yakunlandi'), 400);
    await tester.drag(find.byType(ListView).first, const Offset(0, -400));
    await _save(tester, '06_timeline');
  });

  _screenshot('07 waybill list', (tester) async {
    final app = await _pumpApp(tester, _user('dispatcher', 'Rahimova Nodira'));
    app.router.go(Routes.waybills);
    await tester.pumpAndSettle();
    await _save(tester, '07_waybill_list');
  });

  _screenshot('08 new waybill', (tester) async {
    await _pumpApp(
      tester,
      _user('dispatcher', 'Rahimova Nodira'),
      location: Routes.newWaybill,
    );
    tester
        .element(find.byType(WaybillCreateScreen))
        .read<WaybillCreateViewModel>()
      ..selectVehicle(2)
      ..selectDriver(2);
    await tester.pump();
    await tester.enterText(
      find.widgetWithText(TextField, 'Tabel raqami (ixtiyoriy)'),
      'T-1077',
    );
    await tester.enterText(
      find.widgetWithText(TextField, "Yo'nalish"),
      "Bog'ot magistral liniyasi, 42-km",
    );
    await _save(tester, '08_new_waybill');
  });
}
