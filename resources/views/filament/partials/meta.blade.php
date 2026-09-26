<meta name="author" content="{{ config('app.developer') }}">
<meta name="last-updated" content="{{ \Illuminate\Support\Carbon::parse(config('app.last_update'))->toDateString() }}">
<script>window.BMS_THEMES=@json(array_keys(config('palettes')))</script>
<script>window.FILEPOND_MAX_SIZE_LABEL=@json(__('resources/general/strings.attachments.validation.attachments_size'))</script>
<script>(function(){var t;try{t=localStorage.getItem('theme_palette')}catch(e){return}if(window.BMS_THEMES.indexOf(t)<0)return;document.documentElement.dataset.theme=t})()</script>
