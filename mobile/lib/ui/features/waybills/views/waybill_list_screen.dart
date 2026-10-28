import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../../../data/repositories/auth_repository.dart';
import '../../../../domain/models/permission.dart';
import '../../../../routing/routes.dart';
import '../../../core/feedback.dart';
import '../view_models/waybill_list_view_model.dart';
import 'widgets/waybill_card.dart';

class WaybillListScreen extends StatefulWidget {
  const WaybillListScreen({super.key});

  @override
  State<WaybillListScreen> createState() => _WaybillListScreenState();
}

class _WaybillListScreenState extends State<WaybillListScreen> {
  final _scroll = ScrollController();

  @override
  void initState() {
    super.initState();
    _scroll.addListener(_onScroll);
  }

  @override
  void dispose() {
    _scroll.dispose();
    super.dispose();
  }

  void _onScroll() {
    final viewModel = context.read<WaybillListViewModel>();
    if (!viewModel.loadMoreFailed && _scroll.position.extentAfter < 400) {
      viewModel.loadMore();
    }
  }

  @override
  Widget build(BuildContext context) {
    final viewModel = context.watch<WaybillListViewModel>();
    final canCreate =
        context.watch<AuthRepository>().currentUser?.can(
          Permission.waybillsCreate,
        ) ??
        false;

    return Scaffold(
      appBar: AppBar(
        title: const Text("Yo'l varaqalari"),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(112),
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
                child: TextField(
                  onChanged: viewModel.setSearch,
                  textInputAction: TextInputAction.search,
                  decoration: const InputDecoration(
                    hintText: "Raqam bo'yicha qidirish",
                    prefixIcon: Icon(Icons.search),
                    isDense: true,
                  ),
                ),
              ),
              SizedBox(
                height: 48,
                child: ListView(
                  scrollDirection: Axis.horizontal,
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  children: [
                    for (final status in WaybillListViewModel.filters)
                      Padding(
                        padding: const EdgeInsets.only(right: 8, bottom: 8),
                        child: FilterChip(
                          label: Text(status?.label ?? 'Barchasi'),
                          selected: viewModel.status == status,
                          showCheckmark: false,
                          onSelected: (_) => viewModel.setStatus(status),
                        ),
                      ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
      floatingActionButton: canCreate
          ? FloatingActionButton.extended(
              onPressed: () => context.push(Routes.newWaybill),
              icon: const Icon(Icons.add),
              label: const Text('Yangi varaq'),
            )
          : null,
      body: RefreshIndicator(
        onRefresh: viewModel.refresh,
        child: _buildBody(viewModel),
      ),
    );
  }

  Widget _buildBody(WaybillListViewModel viewModel) {
    final items = viewModel.items;
    if (items.isEmpty) {
      if (viewModel.isLoading) {
        return const Center(child: CircularProgressIndicator());
      }
      if (viewModel.error case final error?) {
        return ErrorRetry(message: error, onRetry: viewModel.refresh);
      }
      return const EmptyState(
        icon: Icons.description_outlined,
        message: "Yo'l varaqalari topilmadi",
      );
    }

    return ListView.separated(
      controller: _scroll,
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
      itemCount: items.length + 1,
      separatorBuilder: (_, _) => const SizedBox(height: 12),
      itemBuilder: (context, index) {
        if (index == items.length) return _buildFooter(viewModel);
        final waybill = items[index];
        return WaybillCard(
          waybill: waybill,
          onTap: () => context.push(Routes.waybill(waybill.id)),
        );
      },
    );
  }

  Widget _buildFooter(WaybillListViewModel viewModel) {
    if (viewModel.error case final error?) return InlineError(message: error);
    if (viewModel.loadMoreFailed) {
      return Center(
        child: TextButton.icon(
          onPressed: viewModel.loadMore,
          icon: const Icon(Icons.refresh),
          label: const Text('Yana yuklash'),
        ),
      );
    }
    if (viewModel.isLoadingMore || viewModel.isLoading) {
      return const Padding(
        padding: EdgeInsets.all(16),
        child: Center(child: CircularProgressIndicator()),
      );
    }
    return const SizedBox.shrink();
  }
}
