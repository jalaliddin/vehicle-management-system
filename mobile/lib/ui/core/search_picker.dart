import 'package:flutter/material.dart';

/// A form field that opens a searchable bottom-sheet list, for pick lists
/// too long for a dropdown (vehicles, drivers, organizations).
class SearchPickerField<T> extends StatelessWidget {
  const SearchPickerField({
    super.key,
    required this.label,
    required this.items,
    required this.itemLabel,
    required this.onSelected,
    this.selected,
    this.itemSubtitle,
    this.errorText,
    this.icon,
    this.enabled = true,
    this.emptyText = "Ro'yxat bo'sh",
  });

  final String label;
  final List<T> items;
  final T? selected;
  final String Function(T) itemLabel;
  final String? Function(T)? itemSubtitle;
  final ValueChanged<T> onSelected;
  final String? errorText;
  final IconData? icon;
  final bool enabled;
  final String emptyText;

  Future<void> _open(BuildContext context) async {
    final picked = await showModalBottomSheet<T>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (context) => _PickerSheet<T>(
        title: label,
        items: items,
        itemLabel: itemLabel,
        itemSubtitle: itemSubtitle,
        emptyText: emptyText,
      ),
    );
    if (picked != null) onSelected(picked);
  }

  @override
  Widget build(BuildContext context) {
    final value = selected;
    return InkWell(
      onTap: enabled ? () => _open(context) : null,
      borderRadius: BorderRadius.circular(12),
      child: InputDecorator(
        isEmpty: value == null,
        decoration: InputDecoration(
          labelText: label,
          errorText: errorText,
          enabled: enabled,
          prefixIcon: icon == null ? null : Icon(icon),
          suffixIcon: const Icon(Icons.arrow_drop_down),
        ),
        child: value == null
            ? null
            : Text(
                itemLabel(value),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
      ),
    );
  }
}

class _PickerSheet<T> extends StatefulWidget {
  const _PickerSheet({
    required this.title,
    required this.items,
    required this.itemLabel,
    required this.itemSubtitle,
    required this.emptyText,
  });

  final String title;
  final List<T> items;
  final String Function(T) itemLabel;
  final String? Function(T)? itemSubtitle;
  final String emptyText;

  @override
  State<_PickerSheet<T>> createState() => _PickerSheetState<T>();
}

class _PickerSheetState<T> extends State<_PickerSheet<T>> {
  String _query = '';

  @override
  Widget build(BuildContext context) {
    final query = _query.toLowerCase();
    final matches = widget.items.where((item) {
      if (query.isEmpty) return true;
      final text =
          '${widget.itemLabel(item)} ${widget.itemSubtitle?.call(item) ?? ''}';
      return text.toLowerCase().contains(query);
    }).toList();

    return SizedBox(
      height: MediaQuery.sizeOf(context).height * 0.75,
      child: Padding(
        padding: EdgeInsets.only(
          bottom: MediaQuery.viewInsetsOf(context).bottom,
        ),
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
              child: TextField(
                autofocus: widget.items.length > 8,
                onChanged: (value) => setState(() => _query = value.trim()),
                decoration: InputDecoration(
                  hintText: widget.title,
                  prefixIcon: const Icon(Icons.search),
                  isDense: true,
                ),
              ),
            ),
            Expanded(
              child: matches.isEmpty
                  ? Center(child: Text(widget.emptyText))
                  : ListView.builder(
                      itemCount: matches.length,
                      itemBuilder: (context, index) {
                        final item = matches[index];
                        final subtitle = widget.itemSubtitle?.call(item);
                        return ListTile(
                          title: Text(widget.itemLabel(item)),
                          subtitle: subtitle == null ? null : Text(subtitle),
                          onTap: () => Navigator.pop(context, item),
                        );
                      },
                    ),
            ),
          ],
        ),
      ),
    );
  }
}
