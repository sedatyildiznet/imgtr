export async function copy(value){await navigator.clipboard.writeText(value);window.toast(window.IMGTR_I18N?.copied||'Copied');}
