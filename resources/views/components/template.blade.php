@if ($rendered)
@if ($head)
@include('lamentor::components.head')
@endif
{!! $rendered->html !!}
@endif
