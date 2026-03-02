<?php

/**
 * BulkDeleteDeviceController.php
 *
 * Controller for the bulk device deletion page.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @link       https://www.librenms.org
 *
 * @copyright  2026 LibreNMS Contributors
 */

namespace App\Http\Controllers;

use App\Facades\LibrenmsConfig;
use App\Models\Device;
use App\Models\DeviceGroup;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BulkDeleteDeviceController extends Controller
{
    /**
     * Display the bulk device deletion page.
     */
    public function index(): View
    {
        $device_groups = DeviceGroup::orderBy('name')->get(['id', 'name']);

        $device_types = collect(LibrenmsConfig::get('device_types', []))
            ->pluck('type')
            ->filter()
            ->sort()
            ->values();

        return view('device.bulk-delete', [
            'device_groups' => $device_groups,
            'device_types' => $device_types,
        ]);
    }

    /**
     * Bulk delete the given devices, honouring per-device authorization.
     */
    public function destroy(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'device_ids' => 'required|array|min:1',
                'device_ids.*' => 'integer|min:1',
            ]);

            $deleted = 0;
            $unauthorized = [];
            $not_found = [];

            foreach ($request->input('device_ids') as $id) {
                $device = Device::find($id);

                if (! $device) {
                    $not_found[] = $id;

                    continue;
                }

                if (Gate::denies('delete', $device)) {
                    $unauthorized[] = $device->displayName();

                    continue;
                }

                try {
                    $device->delete();
                } catch (\Throwable $e) {
                    // DeviceObserver may throw (e.g. Oxidized reload when service is down)
                    // The DB deletion still succeeds, so count it
                    \Log::warning("Bulk delete: post-delete hook error for device $id: " . $e->getMessage());
                }
                $deleted++;
            }

            $response = [
                'deleted' => $deleted,
            ];

            if (! empty($unauthorized)) {
                $response['message'] = __(
                    'Deleted :deleted device(s). Skipped :count due to insufficient permissions.',
                    [
                        'deleted' => $deleted,
                        'count' => count($unauthorized),
                    ]
                );
                $response['status'] = 'warning';
            } elseif (! empty($not_found)) {
                $response['message'] = __('Deleted :deleted device(s). :count device(s) were not found.', [
                    'deleted' => $deleted,
                    'count' => count($not_found),
                ]);
                $response['status'] = 'warning';
            } else {
                $response['message'] = __('Successfully deleted :count device(s).', ['count' => $deleted]);
                $response['status'] = 'ok';
            }

            return response()->json($response);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ], 500);
        }
    }
}
