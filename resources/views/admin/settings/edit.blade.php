<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-slate-800">Company settings</h2>
    </x-slot>

    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="max-w-3xl space-y-6">
        @csrf
        @method('PUT')

        <div class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="font-semibold">Company</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-input-label for="company_name" value="Name" />
                    <x-text-input id="company_name" name="company_name" class="mt-1 block w-full" :value="old('company_name', $settings['company_name'])" required />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="company_address" value="Address" />
                    <textarea id="company_address" name="company_address" rows="2" class="mt-1 block w-full rounded-md border-gray-300">{{ old('company_address', $settings['company_address']) }}</textarea>
                </div>
                <div>
                    <x-input-label for="company_phone" value="Phone" />
                    <x-text-input id="company_phone" name="company_phone" class="mt-1 block w-full" :value="old('company_phone', $settings['company_phone'])" />
                </div>
                <div>
                    <x-input-label for="company_email" value="Email" />
                    <x-text-input id="company_email" name="company_email" type="email" class="mt-1 block w-full" :value="old('company_email', $settings['company_email'])" />
                </div>
                <div>
                    <x-input-label for="currency" value="Currency symbol" />
                    <x-text-input id="currency" name="currency" class="mt-1 block w-full" :value="old('currency', $settings['currency'])" required />
                </div>
                <div>
                    <x-input-label for="currency_code" value="Currency code" />
                    <x-text-input id="currency_code" name="currency_code" class="mt-1 block w-full" :value="old('currency_code', $settings['currency_code'])" required />
                </div>
                <div>
                    <x-input-label for="timezone" value="Timezone" />
                    <x-text-input id="timezone" name="timezone" class="mt-1 block w-full" :value="old('timezone', $settings['timezone'] ?: 'Asia/Colombo')" required />
                </div>
                <div>
                    <x-input-label for="logo" value="Logo" />
                    <input id="logo" type="file" name="logo" accept="image/*" class="mt-1 block w-full text-sm">
                    @if ($settings['company_logo'])
                        <img src="{{ asset('storage/'.$settings['company_logo']) }}" alt="Logo" class="mt-2 h-12">
                        <label class="mt-2 flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" name="remove_logo" value="1" class="rounded border-slate-300">
                            Remove current logo
                        </label>
                    @endif
                </div>
            </div>
        </div>

        <div class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="font-semibold">Document prefixes</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach (['invoice_prefix' => 'Invoice', 'purchase_prefix' => 'Purchase', 'material_request_prefix' => 'Material request', 'material_issue_prefix' => 'Material issue', 'project_prefix' => 'Project', 'worker_prefix' => 'Worker'] as $key => $label)
                    <div>
                        <x-input-label for="{{ $key }}" :value="$label" />
                        <x-text-input id="{{ $key }}" name="{{ $key }}" class="mt-1 block w-full" :value="old($key, $settings[$key])" required />
                    </div>
                @endforeach
            </div>
        </div>

        <x-primary-button>Save settings</x-primary-button>
    </form>
</x-app-layout>
