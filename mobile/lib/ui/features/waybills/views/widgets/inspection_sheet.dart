import 'package:flutter/material.dart';

import '../../../../core/theme.dart';

class InspectionResult {
  const InspectionResult({required this.passed, this.notes});

  final bool passed;
  final String? notes;
}

/// Asks for the mechanic's or doctor's verdict. A rejection needs a reason,
/// because it cancels the waybill.
Future<InspectionResult?> showInspectionSheet(
  BuildContext context, {
  required String title,
  required bool passed,
}) => showModalBottomSheet<InspectionResult>(
  context: context,
  isScrollControlled: true,
  showDragHandle: true,
  builder: (context) => _InspectionSheet(title: title, initiallyPassed: passed),
);

class _InspectionSheet extends StatefulWidget {
  const _InspectionSheet({required this.title, required this.initiallyPassed});

  final String title;
  final bool initiallyPassed;

  @override
  State<_InspectionSheet> createState() => _InspectionSheetState();
}

class _InspectionSheetState extends State<_InspectionSheet> {
  late bool _passed = widget.initiallyPassed;
  final _notes = TextEditingController();
  String? _notesError;

  @override
  void dispose() {
    _notes.dispose();
    super.dispose();
  }

  void _submit() {
    final notes = _notes.text.trim();
    if (!_passed && notes.isEmpty) {
      setState(() => _notesError = 'Rad etish sababini yozing');
      return;
    }
    Navigator.pop(
      context,
      InspectionResult(passed: _passed, notes: notes.isEmpty ? null : notes),
    );
  }

  @override
  Widget build(BuildContext context) {
    final color = _passed ? AppColors.success : AppColors.error;
    return Padding(
      padding: EdgeInsets.fromLTRB(
        20,
        0,
        20,
        20 + MediaQuery.viewInsetsOf(context).bottom,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(widget.title, style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 16),
          SegmentedButton<bool>(
            segments: const [
              ButtonSegment(
                value: true,
                icon: Icon(Icons.check),
                label: Text("Yaroqli"),
              ),
              ButtonSegment(
                value: false,
                icon: Icon(Icons.close),
                label: Text('Rad etish'),
              ),
            ],
            selected: {_passed},
            onSelectionChanged: (selection) => setState(() {
              _passed = selection.first;
              _notesError = null;
            }),
          ),
          const SizedBox(height: 16),
          TextField(
            controller: _notes,
            maxLines: 3,
            textCapitalization: TextCapitalization.sentences,
            decoration: InputDecoration(
              labelText: _passed ? 'Izoh (ixtiyoriy)' : 'Rad etish sababi',
              errorText: _notesError,
            ),
          ),
          const SizedBox(height: 20),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: color),
            onPressed: _submit,
            child: Text(_passed ? 'Tasdiqlash' : 'Rad etish'),
          ),
        ],
      ),
    );
  }
}
