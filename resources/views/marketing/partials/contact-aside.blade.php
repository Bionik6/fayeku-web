<div class="rounded-[2rem] border border-primary/10 bg-white p-8 shadow-soft">
    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-teal">Coordonnees</p>
    <div class="mt-4 space-y-3 text-base text-slate-600">
        <p>{{ $site['contact']['address'] }}</p>
        <p><a href="mailto:{{ $site['contact']['email'] }}" class="hover:text-primary">{{ $site['contact']['email'] }}</a></p>
        <p><a href="mailto:{{ $site['contact']['sales_email'] }}" class="hover:text-primary">{{ $site['contact']['sales_email'] }}</a></p>
    </div>
</div>
