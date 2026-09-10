{{-- 二维码展示弹窗 --}}
<div class="flex flex-col items-center gap-4 py-2">
    <div class="rounded-xl border border-gray-200 bg-white p-3">
        {!! $svg !!}
    </div>

    <div class="w-full">
        <p class="text-center text-sm font-medium text-gray-700">{{ $qr->name }}</p>
        <p class="mt-1 break-all rounded-lg bg-gray-50 px-3 py-2 text-center text-xs text-gray-500">{{ $url }}</p>
    </div>

    <div class="flex w-full gap-3">
        <a href="{{ $dataUri }}" download="qr-{{ $qr->code }}.svg"
           class="fi-btn fi-btn-size-md fi-btn-color-primary fi-ac-btn-action w-full justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white text-center">
            下载二维码
        </a>
        <button type="button"
                onclick="navigator.clipboard.writeText('{{ $url }}'); this.innerText='已复制'"
                class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700">
            复制链接
        </button>
    </div>

    <p class="text-xs text-gray-400">访客扫码后填写登记信息，提交后自动向「{{ $qr->device?->name ?? '未绑定设备（仅登记）' }}」下发指令</p>
</div>
