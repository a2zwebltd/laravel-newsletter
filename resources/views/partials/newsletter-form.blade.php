{{--
    Minimal newsletter subscription form. Posts JSON to the `newsletter.subscribe`
    route. Publish and override this view to add your own captcha widget / styling.
--}}
<form id="newsletter-form" onsubmit="return false;">
    <label for="newsletter-email" class="muted">{{ __('Email address') }}</label>
    <input type="email" id="newsletter-email" name="email" required placeholder="you@example.com">
    <p id="newsletter-message" class="muted" style="min-height:1.2em;"></p>
    <button type="submit" id="newsletter-submit">{{ __('Subscribe') }}</button>
</form>

<script>
    document.getElementById('newsletter-form').addEventListener('submit', function () {
        var email = document.getElementById('newsletter-email').value;
        var message = document.getElementById('newsletter-message');

        fetch('{{ route('newsletter.subscribe') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
            },
            body: JSON.stringify({ email: email }),
        })
            .then(function (r) { return r.json(); })
            .then(function (data) { message.textContent = data.message || '{{ __('Thank you!') }}'; })
            .catch(function () { message.textContent = '{{ __('Something went wrong. Please try again.') }}'; });
    });
</script>
