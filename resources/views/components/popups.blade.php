@foreach ($popups as $popup)
@php($r = $popup['rendered'])
@include('lamentor::components.head', ['rendered' => $r, 'assets' => $assets])
<div class="lamentor-popup" id="lamentor-popup-{{ $popup['id'] }}" role="dialog" aria-modal="true" aria-label="{{ $popup['title'] }}" hidden
     data-trigger="{{ $popup['trigger'] }}" data-delay="{{ $popup['delay'] }}" data-selector="{{ $popup['selector'] }}" data-once="{{ $popup['once'] ? '1' : '0' }}" data-key="lm-popup-{{ $popup['id'] }}-{{ $popup['version'] }}">
    <div class="lamentor-popup-box">
        <button type="button" class="lamentor-popup-close" aria-label="Close">&times;</button>
        {!! $r->html !!}
    </div>
</div>
@endforeach
@if ($popups)
<style>.lamentor-popup{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.55)}.lamentor-popup[hidden]{display:none}.lamentor-popup-box{position:relative;max-width:min(92vw,720px);max-height:90vh;overflow:auto;background:#fff;border-radius:8px;padding:24px}.lamentor-popup-close{position:absolute;top:6px;right:10px;border:0;background:none;font-size:26px;line-height:1;cursor:pointer}</style>
<script>
(function(){
  document.querySelectorAll('.lamentor-popup').forEach(function(p){
    var key=p.dataset.key,once=p.dataset.once==='1',last=null;
    function seen(){try{return once&&localStorage.getItem(key)}catch(e){return false}}
    function open(){if(seen()||!p.hidden)return;last=document.activeElement;p.hidden=false;var c=p.querySelector('.lamentor-popup-close');c&&c.focus()}
    function close(){p.hidden=true;try{once&&localStorage.setItem(key,'1')}catch(e){}last&&last.focus&&last.focus()}
    p.querySelector('.lamentor-popup-close').addEventListener('click',close);
    p.addEventListener('click',function(e){if(e.target===p)close()});
    document.addEventListener('keydown',function(e){if(e.key==='Escape'&&!p.hidden)close()});
    var t=p.dataset.trigger;
    if(t==='load')open();
    else if(t==='delay')setTimeout(open,(parseInt(p.dataset.delay,10)||3)*1000);
    else if(t==='click'&&p.dataset.selector)document.addEventListener('click',function(e){var el=e.target.closest&&e.target.closest(p.dataset.selector);if(el){e.preventDefault();open()}});
    else if(t==='exit')document.addEventListener('mouseout',function(e){if(e.clientY<=0&&!e.relatedTarget)open()});
  });
})();
</script>
@endif
