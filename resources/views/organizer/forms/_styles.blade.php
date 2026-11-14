<style>
  .rf-start{display:grid;grid-template-columns:1.2fr 1fr;gap:16px;margin-bottom:20px}
  .rf-start form{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end}
  .rf-start .t-field{flex:1 1 220px;margin:0}
  .rf-steps{margin:0;padding:0;list-style:none;display:grid;gap:10px;counter-reset:rf}
  .rf-steps li{display:flex;gap:10px;align-items:flex-start;font-size:14px;line-height:20px;color:var(--t-ink,#1d2433)}
  .rf-steps li::before{counter-increment:rf;content:counter(rf);flex:0 0 24px;height:24px;border-radius:999px;background:var(--t-blue-50,#eef3ff);color:var(--t-blue,#2f5bea);font-weight:600;font-size:12px;display:grid;place-items:center}
  .rf-list{display:grid;gap:12px}
  .rf-row{display:flex;gap:16px;align-items:center;justify-content:space-between;flex-wrap:wrap}
  .rf-row h3{margin:0;font-size:16px;line-height:24px;font-weight:600}
  .rf-row h3 a{color:inherit}
  .rf-meta{margin:2px 0 0;color:var(--t-muted,#5b6475);font-size:13px;line-height:18px}
  .rf-chips{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}
  .rf-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
  .rf-actions form{margin:0}
  .rf-builder{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(300px,1fr);gap:20px;align-items:start}
  .rf-fields{list-style:none;margin:0;padding:0;display:grid;gap:8px}
  .rf-field{display:flex;gap:12px;align-items:center;border:1px solid var(--t-line,#e3e7ef);border-radius:10px;padding:10px 12px;background:var(--t-surface,#fff)}
  .rf-field.is-editing{border-color:var(--t-blue,#2f5bea);box-shadow:0 0 0 3px rgba(47,91,234,.12)}
  .rf-field__body{flex:1;min-width:0}
  .rf-field__label{font-weight:600;font-size:14px;line-height:20px;overflow-wrap:anywhere}
  .rf-field__type{font-size:12px;line-height:16px;color:var(--t-muted,#5b6475)}
  .rf-field__tools{display:flex;gap:4px;align-items:center}
  .rf-field__tools form{margin:0}
  .rf-fixed{opacity:.75}
  .rf-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .rf-grid .rf-wide{grid-column:1/-1}
  .rf-events{list-style:none;margin:0 0 12px;padding:0;display:grid;gap:8px;max-height:320px;overflow:auto}
  .rf-events label{display:flex;gap:10px;align-items:flex-start;padding:8px 10px;border:1px solid var(--t-line,#e3e7ef);border-radius:8px;cursor:pointer;font-size:14px;line-height:20px}
  .rf-events input{margin-top:3px}
  .rf-linked{list-style:none;margin:0;padding:0;display:grid;gap:8px}
  .rf-linked li{display:flex;justify-content:space-between;gap:8px;align-items:center;flex-wrap:wrap;font-size:14px;line-height:20px}
  .rf-linked form{margin:0}
  .rf-more summary{cursor:pointer;color:var(--t-blue,#2f5bea);font-weight:600;font-size:14px;margin:4px 0 8px}
  .rf-side{display:grid;gap:16px}
  .rf-errors{border:1px solid #f3c2c2;background:#fff4f4;color:#9b1c1c;border-radius:10px;padding:10px 14px;margin-bottom:16px;font-size:14px}
  .rf-errors ul{margin:0;padding-left:18px}
  .rf-palette{display:grid;gap:14px}
  .rf-palette__label{margin:0 0 6px;font-size:12px;line-height:16px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:var(--t-muted,#5b6475)}
  .rf-palette__items{display:flex;flex-wrap:wrap;gap:8px}
  .rf-chip-form{margin:0}
  .rf-chip{display:inline-flex;align-items:center;gap:6px;min-height:36px;padding:0 12px;border:1px solid var(--t-line,#e3e7ef);border-radius:999px;background:#fff;color:#1d2433;font:500 13.5px/1 inherit;cursor:grab;transition:border-color .15s,background-color .15s}
  .rf-chip span{color:#2E4FD8;font-weight:700}
  .rf-chip:hover{border-color:#2E4FD8;background:#F3F6FF}
  .rf-chip:active{cursor:grabbing}
  .rf-handle{flex:none;width:18px;color:#98A2B3;cursor:grab;font-size:14px;line-height:1;letter-spacing:-2px;user-select:none}
  .rf-handle.is-off{visibility:hidden}
  [data-rf-item]{cursor:default}
  [data-rf-item].is-dragging{opacity:.45}
  .rf-marker{list-style:none;height:4px;margin:2px 0;border-radius:4px;background:#2E4FD8}
  [data-rf-list].is-target{outline:2px dashed #C7D2FE;outline-offset:6px;border-radius:10px}
  .rf-drop-empty{list-style:none;padding:20px;border:1.5px dashed #C7D2FE;border-radius:10px;text-align:center;color:#5b6475;font-size:14px}
  @media (max-width:1023px){.rf-start,.rf-builder{grid-template-columns:1fr}}
  @media (max-width:560px){.rf-grid{grid-template-columns:1fr}}
</style>
