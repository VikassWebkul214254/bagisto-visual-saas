<x-admin::layouts>
    <x-slot:title>
        @lang('visual-saas::app.admin.themes.title')
    </x-slot>

    <div class="flex justify-between items-center">
        <p class="text-xl text-gray-800 dark:text-white font-bold">
            @lang('visual-saas::app.admin.themes.title')
        </p>
    </div>

    <div class="mt-4 grid gap-4 grid-cols-1 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($themes as $theme)
            @php($tenantTheme = $tenantThemes->get($theme->code))

            <div class="rounded-lg border bg-white dark:bg-gray-900 p-4 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-lg font-semibold text-gray-800 dark:text-white">
                            {{ $theme->name }}
                        </p>
                        <p class="text-sm text-gray-500">{{ $theme->code }} — v{{ $theme->version ?? '1.0.0' }}</p>
                    </div>

                    @if ($tenantTheme)
                        <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
                            {{ ucfirst($tenantTheme->status) }}
                        </span>
                    @endif
                </div>

                <div class="mt-4 flex gap-2">
                    <a href="{{ route('admin.visual_saas.themes.edit', ['theme' => $theme->code]) }}"
                       class="inline-flex items-center rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">
                        @lang('visual-saas::app.admin.themes.edit')
                    </a>
                </div>
            </div>
        @endforeach
    </div>
</x-admin::layouts>
