<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingRequest;
use App\Models\Setting;
use App\Traits\LogsActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingController extends Controller
{
    use LogsActivity;

    public function edit(): View
    {
        abort_unless(auth()->user()?->hasRole('admin'), 403);

        $keys = [
            'company_name',
            'company_address',
            'company_phone',
            'company_email',
            'currency',
            'currency_code',
            'invoice_prefix',
            'purchase_prefix',
            'material_request_prefix',
            'material_issue_prefix',
            'project_prefix',
            'worker_prefix',
            'timezone',
            'company_logo',
        ];

        $settings = [];
        foreach ($keys as $key) {
            $settings[$key] = Setting::get($key, '');
        }

        return view('admin.settings.edit', compact('settings'));
    }

    public function update(SettingRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['logo', 'remove_logo']);

        foreach ($data as $key => $value) {
            Setting::set($key, is_string($value) ? trim($value) : $value);
        }

        if ($request->boolean('remove_logo')) {
            $this->deleteLogo();
            Setting::set('company_logo', '');
        }

        if ($request->hasFile('logo')) {
            $this->deleteLogo();
            $path = $request->file('logo')->store('logos', 'public');
            Setting::set('company_logo', $path);
        }

        $this->logActivity('updated', 'Settings', 'Updated company settings');

        return back()->with('success', 'Settings saved.');
    }

    private function deleteLogo(): void
    {
        $current = Setting::get('company_logo');
        if (is_string($current) && $current !== '' && Storage::disk('public')->exists($current)) {
            Storage::disk('public')->delete($current);
        }
    }
}
