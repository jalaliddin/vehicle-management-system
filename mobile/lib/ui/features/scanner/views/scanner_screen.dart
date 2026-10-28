import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:mobile_scanner/mobile_scanner.dart';

import '../../../../domain/models/waybill_link.dart';
import '../../../../routing/routes.dart';

/// Scans the QR code printed on a waybill and opens it, or, with
/// [pickWaybill], pops the waybill id to the screen that asked for it.
class ScannerScreen extends StatefulWidget {
  const ScannerScreen({super.key, this.pickWaybill = false});

  final bool pickWaybill;

  @override
  State<ScannerScreen> createState() => _ScannerScreenState();
}

class _ScannerScreenState extends State<ScannerScreen> {
  final _controller = MobileScannerController(
    formats: const [BarcodeFormat.qrCode],
  );
  bool _opening = false;
  String? _hint;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _onDetect(BarcodeCapture capture) async {
    if (_opening) return;
    final raw = capture.barcodes.map((b) => b.rawValue).nonNulls.firstOrNull;
    if (raw == null) return;

    final id = waybillIdFromQr(raw);
    if (id == null) {
      if (_hint == null) {
        setState(() => _hint = "Bu QR kod yo'l varaqasiga tegishli emas");
      }
      return;
    }
    _opening = true;
    await _controller.stop();
    if (!mounted) return;
    if (widget.pickWaybill) {
      context.pop(id);
    } else {
      context.pushReplacement(Routes.waybill(id));
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: Colors.black,
    appBar: AppBar(
      backgroundColor: Colors.black,
      foregroundColor: Colors.white,
      title: const Text(
        'QR kodni skanerlash',
        style: TextStyle(color: Colors.white),
      ),
      actions: [
        IconButton(
          tooltip: 'Chiroq',
          icon: const Icon(Icons.flashlight_on_outlined),
          onPressed: _controller.toggleTorch,
        ),
      ],
    ),
    body: Stack(
      fit: StackFit.expand,
      children: [
        MobileScanner(
          controller: _controller,
          onDetect: _onDetect,
          errorBuilder: (context, error) => _ScannerError(error: error),
        ),
        const IgnorePointer(child: _Viewfinder()),
        Positioned(
          left: 24,
          right: 24,
          bottom: 48,
          child: Text(
            _hint ?? "Yo'l varaqasidagi QR kodni ramkaga joylang",
            textAlign: TextAlign.center,
            style: const TextStyle(color: Colors.white, fontSize: 16),
          ),
        ),
      ],
    ),
  );
}

class _Viewfinder extends StatelessWidget {
  const _Viewfinder();

  @override
  Widget build(BuildContext context) => Center(
    child: Container(
      width: 260,
      height: 260,
      decoration: BoxDecoration(
        border: Border.all(color: Colors.white, width: 3),
        borderRadius: BorderRadius.circular(24),
      ),
    ),
  );
}

class _ScannerError extends StatelessWidget {
  const _ScannerError({required this.error});

  final MobileScannerException error;

  @override
  Widget build(BuildContext context) {
    final message = switch (error.errorCode) {
      MobileScannerErrorCode.permissionDenied =>
        "Kameraga ruxsat berilmagan. Telefon sozlamalarida ilovaga kamera "
            "ruxsatini bering.",
      MobileScannerErrorCode.unsupported =>
        'Bu qurilmada kamera qo\'llab-quvvatlanmaydi.',
      _ => 'Kamerani ishga tushirib bo\'lmadi.',
    };
    return ColoredBox(
      color: Colors.black,
      child: Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(
                Icons.no_photography_outlined,
                color: Colors.white,
                size: 48,
              ),
              const SizedBox(height: 12),
              Text(
                message,
                textAlign: TextAlign.center,
                style: const TextStyle(color: Colors.white),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
