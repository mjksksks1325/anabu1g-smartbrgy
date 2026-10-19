<script>window.ADMIN_I18N = {{ Illuminate\Support\Js::from(['locale' => app()->getLocale(), 'url' => route('staff.locale'), 'csrfUrl' => route('portal.csrf-token')]) }};</script>
<script src="{{ route('staff.translations') }}"></script>
<script defer src="{{ asset('js/admin-localization.js') }}?v={{ filemtime(public_path('js/admin-localization.js')) }}"></script>
