@extends('layouts.librenmsv1')

@section('title', __('Bulk Delete Devices'))

@section('content')
<div class="container-fluid">

    {{-- Panel header with filter controls --}}
    <div class="panel panel-default panel-condensed">
        <div class="panel-heading" style="padding: 6px 10px;">
            <div class="row">
                <div class="col-sm-3">
                    <select id="filter-group" class="form-control input-sm">
                        <option value="">{{ __('All Groups') }}</option>
                        @foreach($device_groups as $group)
                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-2">
                    <select id="filter-type" class="form-control input-sm">
                        <option value="">{{ __('All Types') }}</option>
                        @foreach($device_types as $type)
                            <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-3">
                    <input id="filter-search" type="text" class="form-control input-sm"
                           placeholder="{{ __('Search hostname / IP…') }}">
                </div>
                <div class="col-sm-4">
                    <button id="btn-apply-filter" class="btn btn-sm btn-primary">
                        <i class="fa fa-search"></i> {{ __('Search') }}
                    </button>
                    <button id="btn-reset-filter" class="btn btn-sm btn-default">
                        <i class="fa fa-times"></i> {{ __('Reset') }}
                    </button>
                    <button id="btn-select-all" class="btn btn-sm btn-default" style="margin-left:4px;">
                        <i class="fa fa-check-square-o"></i> {{ __('Select All') }}
                    </button>
                    <span id="selection-info" style="display:none; margin-left:8px;">
                        <strong id="selected-count-text" class="text-danger"></strong>
                        <button id="btn-delete-selected" class="btn btn-sm btn-danger" style="margin-left:6px;">
                            <i class="fa fa-trash"></i> {{ __('Delete Selected') }}
                        </button>
                        <button id="btn-clear-selection" class="btn btn-sm btn-default">
                            <i class="fa fa-times"></i> {{ __('Clear') }}
                        </button>
                    </span>
                    <span id="bootgrid-actions-slot" class="pull-right"></span>
                </div>
            </div>
        </div>

        {{-- Device table: x-ignore tells Alpine to skip this entire subtree --}}
        <div class="table-responsive" x-ignore>
            <table id="bulk-delete-table" class="table table-condensed table-hover table-striped bootgrid-table">
                <thead>
                <tr>
                    <th data-column-id="check" data-formatter="checkboxFormatter"
                        data-sortable="false" data-searchable="false" data-width="30px">
                        <input type="checkbox" id="check-all"
                               title="{{ __('Select / deselect all on this page') }}">
                    </th>
                    <th data-column-id="extra" data-formatter="extraFormatter"
                        data-sortable="false" data-searchable="false" data-width="18px">&nbsp;</th>
                    <th data-column-id="device_id" data-sortable="true" data-width="50px">{{ __('Id') }}</th>
                    <th data-column-id="icon" data-formatter="htmlFormatter"
                        data-sortable="false" data-searchable="false" data-width="40px"></th>
                    <th data-column-id="hostname" data-formatter="htmlFormatter"
                        data-sortable="true" data-identifier="true">{{ __('Device') }}</th>
                    <th data-column-id="metrics" data-formatter="htmlFormatter"
                        data-sortable="false" data-searchable="false">{{ __('Metrics') }}</th>
                    <th data-column-id="os" data-formatter="htmlFormatter" data-sortable="true">
                        {{ __('Operating System') }}
                    </th>
                    <th data-column-id="uptime" data-sortable="true">{{ __('Uptime') }}</th>
                    <th data-column-id="location" data-sortable="true">{{ __('Location') }}</th>
                </tr>
                </thead>
            </table>
        </div>
    </div>

</div>

{{-- Confirmation modal --}}
<div class="modal fade" id="confirm-delete-modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title">
                    <i class="fa fa-exclamation-triangle text-danger"></i>
                    {{ __('Confirm Bulk Delete') }}
                </h4>
            </div>
            <div class="modal-body">
                <p id="confirm-delete-text"></p>
                <div class="alert alert-danger" style="margin-bottom:0">
                    <i class="fa fa-warning"></i>
                    {{ __('This cannot be undone. All data for the selected devices will be permanently removed.') }}
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-danger" id="btn-confirm-delete">
                    <i class="fa fa-trash"></i> {{ __('Yes, Delete') }}
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    var selectedDeviceIds = {};

    function updateSelectionBar() {
        var count = Object.keys(selectedDeviceIds).length;
        if (count > 0) {
            $('#selection-info').show();
            $('#selected-count-text').text(count + ' {{ __('selected') }}');
        } else {
            $('#selection-info').hide();
        }
    }

    function buildPost() {
        var d = {};
        var g = $('#filter-group').val();
        var t = $('#filter-type').val();
        var s = $('#filter-search').val();
        if (g) { d.group = g; }
        if (t) { d.type  = t; }
        if (s) { d.searchPhrase = s; }
        d.format = 'list_detail';
        return d;
    }

    $(document).ready(function () {
        var grid = $('#bulk-delete-table').bootgrid({
            ajax: true,
            rowCount: [25, 50, 100, -1],
            url: '{{ route('table.device') }}',
            formatters: {
                checkboxFormatter: function (column, row) {
                    var checked = selectedDeviceIds.hasOwnProperty(String(row.device_id)) ? ' checked' : '';
                    return '<input type="checkbox" class="row-select" data-id="' + row.device_id + '"' + checked + '>';
                },
                htmlFormatter: function (column, row) {
                    return row[column.id];
                },
                extraFormatter: function (column, row) {
                    var statusClass = row.extra || 'label-default';
                    return '<span class="alert-status ' + statusClass +
                           '" style="width:7px;height:32px;display:inline-block;"></span>';
                },
            },
            post: buildPost,
            requestHandler: function (request) {
                return $.extend(request, buildPost());
            },
            responseHandler: function (response) {
                // Strip Alpine.js tooltip directives from the raw HTML payload before rendering
                // Use a global RegExp to ensure all occurrences across different columns/elements are removed
                if (response.rows) {
                    $.each(response.rows, function (i, row) {
                        $.each(row, function (key, val) {
                            if (typeof val === 'string' && val.indexOf('x-data="deviceLink()"') !== -1) {
                                response.rows[i][key] = val.replace(/x-data="deviceLink\(\)"/g, '');
                            }
                        });
                    });
                }
                return response;
            }
        }).on('loaded.rs.jquery.bootgrid', function () {
            // Move action bar into filter row once on first load
            if ($('#bootgrid-actions-slot').children().length === 0) {
                $('#bootgrid-actions-slot').append($('#bulk-delete-table-header .actionBar'));
                $('#bootgrid-actions-slot .search').hide(); // .search travels with .actionBar
                $('#bulk-delete-table-header').hide();
            }
            // Don't run Alpine on this table — popups handled by CSS suppression below
            $('#bulk-delete-table').find('input.row-select').on('change', function () {
                var id = String($(this).data('id'));
                if ($(this).is(':checked')) {
                    selectedDeviceIds[id] = id;
                } else {
                    delete selectedDeviceIds[id];
                }
                updateSelectionBar();
                syncCheckAll();
            });
            syncCheckAll();
        });

        function syncCheckAll() {
            var cbs = $('#bulk-delete-table').find('input.row-select');
            var all = cbs.length > 0 && cbs.filter(':not(:checked)').length === 0;
            var any = cbs.filter(':checked').length > 0;
            $('#check-all').prop('checked', all).prop('indeterminate', !all && any);
        }

        $('#check-all').on('change', function () {
            var checked = $(this).is(':checked');
            $('#bulk-delete-table').find('input.row-select').each(function () {
                $(this).prop('checked', checked);
                var id = String($(this).data('id'));
                if (checked) { selectedDeviceIds[id] = id; }
                else         { delete selectedDeviceIds[id]; }
            });
            updateSelectionBar();
        });

        $('#btn-select-all').on('click', function () {
            var $checkboxes = $('#bulk-delete-table').find('input.row-select');
            var allChecked = $checkboxes.length > 0
                && $checkboxes.filter(':not(:checked)').length === 0;
            $checkboxes.each(function () {
                $(this).prop('checked', !allChecked);
                var id = String($(this).data('id'));
                if (!allChecked) { selectedDeviceIds[id] = id; }
                else             { delete selectedDeviceIds[id]; }
            });
            syncCheckAll();
            updateSelectionBar();
        });

        $('#btn-apply-filter').on('click', function () { grid.bootgrid('reload'); });

        $('#filter-search').on('keypress', function (e) {
            if (e.which === 13) { grid.bootgrid('reload'); }
        });

        $('#btn-reset-filter').on('click', function () {
            $('#filter-group, #filter-type').val('');
            $('#filter-search').val('');
            grid.bootgrid('reload');
        });

        $('#btn-clear-selection').on('click', function () {
            selectedDeviceIds = {};
            $('#bulk-delete-table').find('input.row-select').prop('checked', false);
            $('#check-all').prop({ checked: false, indeterminate: false });
            updateSelectionBar();
        });

        $('#btn-delete-selected').on('click', function () {
            var count = Object.keys(selectedDeviceIds).length;
            if (!count) { return; }
            $('#confirm-delete-text').text(
                '{{ __('You are about to permanently delete') }} '
                + count + ' {{ __('device(s). Are you sure?') }}'
            );
            $('#confirm-delete-modal').modal('show');
        });

        $('#btn-confirm-delete').on('click', function () {
            $('#confirm-delete-modal').modal('hide');
            $.ajax({
                url:  '{{ route('device.bulk-delete.destroy') }}',
                type: 'POST',
                data: {
                    _token:  '{{ csrf_token() }}',
                    device_ids: Object.keys(selectedDeviceIds),
                },
                success: function (resp) {
                    if (resp.status === 'ok') { toastr.success(resp.message); }
                    else                      { toastr.warning(resp.message); }
                    selectedDeviceIds = {};
                    updateSelectionBar();
                    grid.bootgrid('reload');
                },
                error: function (xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.message)
                        ? xhr.responseJSON.message
                        : '{{ __('An error occurred during deletion.') }}';
                    toastr.error(msg);
                }
            });
        });
    });
</script>
@endsection

@section('css')
<style>
    /* match first cell alignment for status bar and checkbox */
    #bulk-delete-table th:first-child,
    #bulk-delete-table td:first-child { text-align: center; vertical-align: middle; width: 30px; }
    #bulk-delete-table .alert-status { vertical-align: middle; }
    #bulk-delete-table-header .search,
    #bootgrid-actions-slot .search { display: none !important; }
    /* once transplanted, the header will be hidden by JS; collapse it to prevent flash */
    #bulk-delete-table-header { padding: 0; }
    /* text in cells should match the main device list exactly */
    #bulk-delete-table td { color: #555 !important; font-weight: 400 !important; }
    #bulk-delete-table td a { color: #1c398e !important; }
    /* metrics icons/links should appear in the same grey as plain text */
    #bulk-delete-table .device-table-metrics,
    #bulk-delete-table .device-table-metrics a { color: #555 !important; }
    /* suppress Alpine popup elements — we don't need hover popups on this page */
    #bulk-delete-table [x-show],
    #bulk-delete-table .popup { display: none !important; }
</style>
@endsection
