@once
<script{!! $nonce ? ' nonce="'.e($nonce).'"' : '' !!} data-share-messages="{{ json_encode($messages, JSON_UNESCAPED_UNICODE) }}">
{!! $source !!}
</script>
@endonce
