<style>
  /* Registration forms. TICA tokens: white cards on light grey, light lines,
     navy headings, calm grey-blue text, TICA blue for actions only. */
  .rf-start{display:grid;grid-template-columns:1.2fr 1fr;gap:16px;margin-bottom:20px}
  .rf-start form{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end}
  .rf-start .t-field{flex:1 1 220px;margin:0}
  .rf-steps{margin:0;padding:0;list-style:none;display:grid;gap:10px;counter-reset:rf}
  .rf-steps li{display:flex;gap:10px;align-items:flex-start;font-size:14px;line-height:20px;color:#526079}
  .rf-steps li::before{counter-increment:rf;content:counter(rf);flex:0 0 24px;height:24px;border-radius:999px;background:var(--t-blue-50,#EEF2FF);color:var(--t-blue,#2E4FD8);font-weight:600;font-size:12px;display:grid;place-items:center}
  .rf-list{display:grid;gap:12px}
  .rf-row{display:flex;gap:16px;align-items:center;justify-content:space-between;flex-wrap:wrap}
  .rf-row h3{margin:0;font-size:16px;line-height:24px;font-weight:600;color:#0B2447}
  .rf-row h3 a{color:inherit}
  .rf-meta{display:block;margin:2px 0 0;color:var(--t-muted,#667085);font-size:13px;line-height:18px;font-weight:400}
  .rf-chips{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}
  .rf-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
  .rf-actions form{margin:0}
  .rf-errors{border:1px solid #F4C7C3;background:var(--t-red-50,#FEF3F2);color:var(--t-red,#B42318);border-radius:10px;padding:10px 14px;margin-bottom:16px;font-size:14px}
  .rf-errors ul{margin:0;padding-left:18px}

  /* Editor page */
  .rf-page .t-card{border:1px solid var(--t-line,#E3E7ED);border-radius:14px;background:#fff;padding:22px 24px;box-shadow:none}
  .rf-page .t-card__head{margin-bottom:4px;align-items:center}
  .rf-page .t-card__head ~ :not(.t-help):not(#rf-order):first-of-type{margin-top:12px}
  .rf-page .t-card > form:first-of-type:not(#rf-order), .rf-page .t-card > .rf-linked, .rf-page .t-card > .rf-actions{margin-top:14px}
  .rf-page .t-card__title{color:#0B2447;font-weight:600;font-size:16px;line-height:24px}
  .rf-page .t-card .t-help{margin:0 0 16px;color:var(--t-muted,#667085);font-size:13px;line-height:18px}
  .rf-page .t-label{color:#0B2447;font-size:13px;font-weight:500}
  .rf-head h1{color:#0B2447}
  .rf-head p{color:#526079}
  .rf-head a{color:var(--t-blue,#2E4FD8);font-weight:500}
  .rf-builder{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(300px,1fr);gap:20px;align-items:start}
  .rf-side{display:grid;gap:16px;min-width:0}

  /* Field picker */
  .rf-palette{display:grid;gap:16px}
  .rf-palette__label{margin:0 0 8px;font-size:11.5px;line-height:16px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:var(--t-muted,#667085)}
  .rf-palette__items{display:flex;flex-wrap:wrap;gap:8px}
  .rf-chip-form{margin:0}
  .rf-chip{display:inline-flex;align-items:center;gap:6px;min-height:36px;padding:0 14px;border:1px solid var(--t-line,#E3E7ED);border-radius:999px;background:#fff;color:#0B2447;font-family:inherit;font-size:13.5px;font-weight:500;line-height:1;cursor:grab;transition:border-color .15s,background-color .15s}
  .rf-chip span{color:var(--t-blue,#2E4FD8);font-weight:700}
  .rf-chip:hover{border-color:var(--t-blue,#2E4FD8);background:var(--t-blue-50,#EEF2FF)}
  .rf-chip:focus-visible{outline:2px solid var(--t-blue,#2E4FD8);outline-offset:2px}
  .rf-chip:active{cursor:grabbing}

  /* Fields in this form */
  .rf-fields{list-style:none;margin:0;padding:0;display:grid;gap:8px}
  .rf-field{display:flex;gap:12px;align-items:center;min-height:60px;border:1px solid var(--t-line,#E3E7ED);border-radius:10px;padding:10px 12px;background:#fff}
  .rf-field.is-editing{border-color:var(--t-blue,#2E4FD8);box-shadow:0 0 0 3px rgba(46,79,216,.12)}
  .rf-field__body{flex:1;min-width:0}
  .rf-field__label{font-weight:600;font-size:14px;line-height:20px;color:#0B2447;overflow-wrap:anywhere}
  .rf-req{color:var(--t-muted,#667085);font-weight:500}
  .rf-field__type{font-size:12.5px;line-height:18px;color:var(--t-muted,#667085)}
  .rf-field__tools{display:flex;gap:4px;align-items:center;flex-wrap:wrap;justify-content:flex-end}
  .rf-field__tools form{margin:0}
  .rf-fixed{background:var(--t-subtle,#F1F3F6);border-color:transparent}
  .rf-pill{flex:none;padding:3px 10px;border-radius:999px;background:#fff;border:1px solid var(--t-line,#E3E7ED);color:#526079;font-size:12px;font-weight:500}
  .rf-handle{flex:none;display:grid;place-items:center;width:20px;height:28px;color:#98A2B3;cursor:grab;user-select:none}
  .rf-handle svg{width:18px;height:18px;fill:currentColor}
  .rf-handle.is-off{cursor:default}
  .rf-handle.is-off svg{width:16px;height:16px;fill:none;stroke:currentColor;stroke-width:1.8}
  .rf-icon{display:inline-grid;place-items:center;width:32px;height:32px;border:1px solid var(--t-line,#E3E7ED);border-radius:8px;background:#fff;color:#526079;font-size:14px;cursor:pointer}
  .rf-icon:hover:not([disabled]){background:var(--t-subtle,#F1F3F6);color:#0B2447}
  .rf-icon[disabled]{opacity:.35;cursor:default}
  .rf-page .rf-danger{color:var(--t-red,#B42318)!important}
  .rf-page .rf-danger:hover{background:var(--t-red-50,#FEF3F2)!important}
  [data-rf-item]{cursor:default}
  [data-rf-item].is-dragging{opacity:.45}
  .rf-marker{list-style:none;height:4px;margin:2px 0;border-radius:4px;background:var(--t-blue,#2E4FD8)}
  [data-rf-list].is-target{outline:2px dashed #C7D2FE;outline-offset:6px;border-radius:10px}
  .rf-drop-empty{list-style:none;padding:20px;border:1.5px dashed var(--t-line-strong,#CBD2DC);border-radius:10px;text-align:center;color:var(--t-muted,#667085);font-size:14px}

  /* Field editor */
  .rf-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px 12px}
  .rf-grid .t-field{margin:0}
  .rf-grid .rf-wide{grid-column:1/-1}
  .rf-more{margin:16px 0;border-top:1px solid var(--t-line,#E3E7ED);padding-top:12px}
  .rf-more summary{cursor:pointer;color:var(--t-blue,#2E4FD8);font-weight:600;font-size:14px;margin:0 0 12px}

  /* Checkboxes with a sentence beside them */
  .rf-page .rf-check{display:flex;gap:10px;align-items:flex-start;width:auto;height:auto;margin:0;font-size:14px;line-height:20px;font-weight:400;color:#526079;cursor:pointer}
  .rf-check input{flex:none;width:18px;height:18px;margin:1px 0 0;accent-color:var(--t-blue,#2E4FD8)}
  .rf-check span{min-width:0}
  .rf-page .rf-check--inline{align-self:end;min-height:44px;align-items:center;color:#0B2447;font-weight:500}
  .rf-check--inline input{margin:0}
  .rf-checks{display:grid;gap:12px;margin:0 0 16px;padding:14px;border-radius:10px;background:var(--t-subtle,#F1F3F6)}

  /* Events */
  .rf-events{list-style:none;margin:0 0 12px;padding:0;display:grid;gap:8px;max-height:320px;overflow:auto}
  .rf-page .rf-events label{display:flex;gap:10px;align-items:flex-start;margin:0;font-weight:400;color:#526079;padding:12px 14px;border:1px solid var(--t-line,#E3E7ED);border-radius:10px;cursor:pointer;font-size:14px;line-height:20px;transition:border-color .15s,background-color .15s}
  .rf-events label:hover{border-color:var(--t-line-strong,#CBD2DC)}
  .rf-events label:has(input:checked){border-color:var(--t-blue,#2E4FD8);background:var(--t-blue-50,#EEF2FF)}
  .rf-events input{flex:none;width:18px;height:18px;margin:1px 0 0;accent-color:var(--t-blue,#2E4FD8)}
  .rf-events__text{min-width:0}
  .rf-events__text b,.rf-linked__text b{color:#0B2447;font-weight:600}
  .rf-linked{list-style:none;margin:0;padding:0;display:grid}
  .rf-linked li{display:flex;justify-content:space-between;gap:8px 12px;align-items:center;flex-wrap:wrap;padding:12px 0;border-top:1px solid var(--t-line,#E3E7ED);font-size:14px;line-height:20px}
  .rf-linked li:first-child{border-top:0;padding-top:0}
  .rf-linked__text{min-width:0}
  .rf-linked form{margin:0}
  .rf-push{margin-top:16px;padding-top:16px;border-top:1px solid var(--t-line,#E3E7ED)}
  .rf-push .t-help{margin:8px 0 0!important}

  @media (max-width:1023px){.rf-start,.rf-builder{grid-template-columns:1fr}}
  @media (max-width:560px){
    .rf-grid{grid-template-columns:1fr}
    .rf-page .t-card{padding:16px}
    .rf-field{flex-wrap:wrap}
    .rf-field__body{flex:1 1 160px}
    .rf-fixed .rf-pill{display:none}
    .rf-field__tools{width:100%;justify-content:flex-start;padding-left:32px}
  }
</style>
