<style>
  /* Guest list & RSVPs. TICA tokens: white cards on light grey, navy headings,
     calm grey-blue text, TICA blue for actions and data only. */
  .gl{--gl-ink:#0B2447;--gl-text:#526079;--gl-muted:var(--t-muted,#667085);--gl-line:var(--t-line,#E3E7ED);--gl-soft:var(--t-subtle,#F1F3F6);--gl-blue:var(--t-blue,#2E4FD8);--gl-blue-50:var(--t-blue-50,#EEF2FF);--gl-ease:cubic-bezier(.2,.7,.2,1);
    display:grid;gap:16px;max-width:1280px;color:var(--gl-text);container-type:inline-size;container-name:gl}
  .gl.is-loading{opacity:.6;transition:opacity .2s}
  .gl>*{animation:glRise .38s var(--gl-ease) both}
  .gl>*:nth-child(2){animation-delay:.04s}.gl>*:nth-child(3){animation-delay:.08s}.gl>*:nth-child(4){animation-delay:.12s}.gl>*:nth-child(n+5){animation-delay:.16s}
  @keyframes glRise{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}

  .gl-head{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap}
  .gl-head h1{margin:0;color:var(--gl-ink);font-size:28px;line-height:36px;font-weight:600;letter-spacing:-.015em}
  .gl-head p{margin:4px 0 0;color:var(--gl-text);font-size:14px}
  .gl-head__actions{display:flex;gap:8px;flex-wrap:wrap}
  .gl-head__actions .t-btn svg{width:16px;height:16px;margin-right:4px;fill:none;stroke:currentColor;stroke-width:2.2;stroke-linecap:round}

  .gl-card{min-width:0;border:1px solid var(--gl-line);border-radius:14px;background:#fff}
  .gl-event{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
  .gl .gl-event label{margin:0;color:var(--gl-muted);font-size:13px;font-weight:500}
  .gl-event .t-select{width:auto;min-width:min(360px,100%);max-width:100%;background-color:#fff}

  .gl-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
  .gl-stat{position:relative;display:grid;align-content:start;gap:2px;padding:16px 18px;border:1px solid var(--gl-line);border-radius:14px;background:#fff}
  .gl-stat span{color:var(--gl-muted);font-size:13px;font-weight:500}
  .gl-stat b{color:var(--gl-ink);font-size:26px;line-height:34px;font-weight:600;font-variant-numeric:tabular-nums}
  .gl-stat small{color:var(--gl-muted);font-size:12.5px}
  .gl-meter{display:block;height:4px;margin-top:8px;border-radius:999px;background:var(--gl-soft);overflow:hidden}
  .gl-meter i{display:block;height:100%;border-radius:inherit;background:var(--gl-blue);transform-origin:left;animation:glGrow .8s .2s var(--gl-ease) both}
  @keyframes glGrow{from{transform:scaleX(0)}to{transform:scaleX(1)}}

  .gl-list{padding:0;overflow:hidden}
  .gl-list__head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:18px 22px 14px;border-bottom:1px solid var(--gl-line)}
  .gl-list__head h2{margin:0;color:var(--gl-ink);font-size:16px;line-height:24px;font-weight:600}
  .gl-tabs{display:flex;gap:4px;flex-wrap:wrap;padding:3px;border-radius:10px;background:var(--gl-soft)}
  .gl-tab{display:inline-flex;align-items:center;gap:6px;min-height:32px;padding:0 12px;border-radius:8px;color:var(--gl-text)!important;font-size:13px;font-weight:500;text-decoration:none!important;transition:background-color .18s,color .18s,box-shadow .18s}
  .gl-tab span{color:var(--gl-muted);font-size:12px;font-variant-numeric:tabular-nums}
  .gl-tab:hover{color:var(--gl-ink)!important}
  .gl-tab.is-on{background:#fff;color:var(--gl-ink)!important;box-shadow:0 1px 2px rgba(11,36,71,.08)}
  .gl-tab.is-on span{color:var(--gl-blue)}

  .gl-table{display:grid}
  .gl-row{display:grid;grid-template-columns:minmax(220px,2.2fr) minmax(90px,.8fr) minmax(130px,1fr) minmax(90px,.8fr) minmax(150px,auto);align-items:center;gap:12px;padding:12px 22px;border-top:1px solid var(--gl-line);font-size:14px;transition:background-color .18s}
  .gl-row:first-child{border-top:0}
  .gl-row:not(.gl-row--head):hover{background:#FAFBFC}
  .gl-row--head{padding-top:10px;padding-bottom:10px;background:var(--gl-soft);color:var(--gl-muted);font-size:12px;font-weight:600;letter-spacing:.02em}
  .gl-guest{display:flex;align-items:center;gap:12px;min-width:0}
  .gl-avatar{flex:none;display:grid;place-items:center;width:36px;height:36px;border-radius:999px;background:var(--gl-blue-50);color:var(--gl-blue);font-size:13px;font-weight:600}
  .gl-guest__text{display:grid;min-width:0}
  .gl-guest__text b{color:var(--gl-ink);font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .gl-guest__text small{color:var(--gl-muted);font-size:12.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .gl-cell{display:flex;flex-direction:column;align-items:flex-start;gap:4px;min-width:0;color:var(--gl-text)}
  .gl-cell__label{display:none}
  .gl-muted{color:var(--gl-muted)}
  .gl-note{color:var(--gl-muted);font-size:12px}
  .gl-pill{display:inline-flex;align-items:center;padding:3px 10px;border-radius:999px;font-size:12px;line-height:16px;font-weight:600;white-space:nowrap}
  .gl-pill--green{background:var(--t-green-50,#ECFDF3);color:var(--t-green,#067647)}
  .gl-pill--amber{background:var(--t-orange-50,#FFF6ED);color:var(--t-orange,#C4320A)}
  .gl-pill--grey{background:var(--gl-soft);color:var(--gl-text)}
  .gl-pill--blue{background:var(--gl-blue-50);color:var(--gl-blue)}
  .gl-pill--red{background:var(--t-red-50,#FEF3F2);color:var(--t-red,#B42318)}
  .gl-actions{display:flex;align-items:center;justify-content:flex-end;gap:6px}
  .gl-actions form{margin:0}
  .gl-link{min-height:32px;padding:0 10px;border:0;border-radius:8px;background:transparent;color:var(--gl-text);font:500 13px/1 inherit;font-family:inherit;cursor:pointer;transition:background-color .18s,color .18s}
  .gl-link--danger{color:var(--t-red,#B42318)}
  .gl-link--danger:hover{background:var(--t-red-50,#FEF3F2)}
  .gl .is-sure{background:var(--t-red,#B42318)!important;border-color:var(--t-red,#B42318)!important;color:#fff!important}
  .gl [aria-busy=true],.gl-dlg [aria-busy=true]{opacity:.7;cursor:progress}
  .gl-pages{padding:12px 22px;border-top:1px solid var(--gl-line)}
  .gl-pages nav{display:flex;justify-content:flex-end}
  .gl-pages .pagination{margin:0}
  .gl-privacy{display:flex;align-items:center;gap:8px;margin:0;padding:12px 22px;border-top:1px solid var(--gl-line);background:#FAFBFC;color:var(--gl-muted);font-size:12.5px}
  .gl-privacy svg{flex:none;width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:1.8}

  .gl-empty{display:grid;justify-items:center;gap:6px;padding:40px 24px;text-align:center}
  .gl-empty h2,.gl-empty h3{margin:6px 0 0;color:var(--gl-ink);font-size:17px;font-weight:600}
  .gl-empty p{margin:0 0 10px;max-width:420px;color:var(--gl-text);font-size:14px}
  .gl-empty__icon{display:grid;place-items:center;width:48px;height:48px;border-radius:14px;background:var(--gl-blue-50);color:var(--gl-blue)}
  .gl-empty__icon svg{width:24px;height:24px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round}
  .gl-empty__actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:center}

  .gl-imports summary{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 22px;cursor:pointer;list-style:none}
  .gl-imports summary::-webkit-details-marker{display:none}
  .gl-imports summary b{display:block;color:var(--gl-ink);font-weight:600}
  .gl-imports summary small{color:var(--gl-muted);font-size:13px}
  .gl-imports summary svg{width:18px;height:18px;fill:none;stroke:var(--gl-text);stroke-width:2;transition:transform .2s var(--gl-ease)}
  .gl-imports[open] summary svg{transform:rotate(180deg)}
  .gl-imports>ul{margin:0;padding:0 22px 8px;list-style:none;border-top:1px solid var(--gl-line)}
  .gl-imports>ul>li{padding:12px 0;border-top:1px solid var(--gl-line)}
  .gl-imports>ul>li:first-child{border-top:0}
  .gl-imports__row{display:flex;align-items:center;justify-content:space-between;gap:12px}
  .gl-imports__row b{display:block;color:var(--gl-ink);font-weight:600;font-size:14px}
  .gl-imports__row small{color:var(--gl-muted);font-size:12.5px}
  .gl-imports__errors{margin:8px 0 0;padding:10px 14px;border-radius:10px;background:var(--gl-soft);list-style:none;color:var(--gl-text);font-size:12.5px;line-height:18px}

  /* Dialogs */
  .gl-dlg{width:min(520px,calc(100% - 24px));max-height:calc(100vh - 48px);padding:0;border:0;border-radius:16px;background:#fff;color:#526079;box-shadow:0 24px 60px rgba(11,36,71,.18)}
  .gl-dlg::backdrop{background:rgba(11,36,71,.32)}
  .gl-dlg[open]{animation:glDlg .24s cubic-bezier(.2,.7,.2,1) both}
  .gl-dlg[open]::backdrop{animation:glFade .24s ease both}
  @keyframes glDlg{from{opacity:0;transform:translateY(10px) scale(.98)}to{opacity:1;transform:none}}
  @keyframes glFade{from{opacity:0}to{opacity:1}}
  .gl-dlg__form{display:flex;flex-direction:column;max-height:calc(100vh - 48px);margin:0}
  .gl-dlg__head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:20px 24px 6px}
  .gl-dlg__head h2{margin:0;color:#0B2447;font-size:18px;line-height:26px;font-weight:600}
  .gl-dlg__head p{margin:2px 0 0;color:var(--t-muted,#667085);font-size:13px;line-height:18px}
  .gl-x{flex:none;display:grid;place-items:center;width:36px;height:36px;border:0;border-radius:999px;background:var(--t-subtle,#F1F3F6);color:#526079;cursor:pointer}
  .gl-x svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round}
  .gl-dlg__body{display:grid;gap:14px;padding:14px 24px;overflow:auto}
  .gl-dlg__foot{display:flex;justify-content:flex-end;gap:8px;padding:14px 24px 20px;border-top:1px solid var(--t-line,#E3E7ED)}
  .gl-dlg .gl-field{display:grid;gap:6px;margin:0;font-weight:400}
  .gl-dlg .gl-field>span{color:#0B2447;font-size:13px;font-weight:500}
  .gl-dlg .gl-field small{color:var(--t-muted,#667085);font-size:12.5px}
  .gl-dlg .gl-field--short .t-input{max-width:140px}
  .gl-dlg .gl-err{color:var(--t-red,#B42318)!important;font-size:12.5px}
  .gl-dlg .gl-check{display:flex;gap:10px;align-items:flex-start;margin:0;color:#526079;font-size:14px;line-height:20px;font-weight:400;cursor:pointer}
  .gl-dlg .gl-check input{flex:none;width:18px;height:18px;margin:1px 0 0;accent-color:var(--t-blue,#2E4FD8)}
  .gl-two{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .gl-template{display:flex;align-items:center;gap:12px;padding:12px 14px;border:1px solid var(--t-line,#E3E7ED);border-radius:12px;color:#526079!important;text-decoration:none!important;transition:border-color .18s,background-color .18s}
  .gl-template:hover{border-color:var(--t-blue,#2E4FD8);background:var(--t-blue-50,#EEF2FF)}
  .gl-template svg{flex:none;width:20px;height:20px;fill:none;stroke:var(--t-blue,#2E4FD8);stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
  .gl-template b{display:block;color:#0B2447;font-size:14px;font-weight:600}
  .gl-template small{color:var(--t-muted,#667085);font-size:12.5px}
  .gl-drop{position:relative;display:grid;justify-items:center;gap:8px;margin:0;padding:24px 16px;border:1.5px dashed var(--t-line-strong,#CBD2DC);border-radius:12px;color:#526079;font-size:14px;font-weight:400;text-align:center;cursor:pointer;transition:border-color .18s,background-color .18s}
  .gl-drop input{position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer}
  .gl-drop svg{width:22px;height:22px;fill:none;stroke:var(--t-blue,#2E4FD8);stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
  .gl-drop:hover,.gl-drop.is-over{border-color:var(--t-blue,#2E4FD8);background:var(--t-blue-50,#EEF2FF)}
  .gl-drop.has-file{border-style:solid;border-color:var(--t-blue,#2E4FD8);color:#0B2447;font-weight:500}
  .gl-drop:focus-within{outline:2px solid var(--t-blue,#2E4FD8);outline-offset:2px}

  /* Responsive, by the page's own width */
  @container gl (max-width:900px){
    .gl-stats{grid-template-columns:repeat(2,minmax(0,1fr))}
    .gl-row{display:flex;flex-wrap:wrap;align-items:center;gap:10px 18px;padding:14px 18px}
    .gl-row--head{display:none}
    .gl-row>.gl-guest{flex:1 1 100%}
    .gl-row>.gl-cell{flex:0 1 auto;flex-direction:row;flex-wrap:wrap;align-items:center;gap:4px 6px;padding-left:0}
    .gl-cell__label{display:inline;color:var(--gl-muted);font-size:12px}
    .gl-cell .gl-note{flex-basis:100%}
    .gl-row>.gl-actions{flex:1 1 100%;justify-content:flex-start}
    .gl-row>.gl-actions:empty{display:none}
    .gl-list__head,.gl-privacy,.gl-pages{padding-left:18px;padding-right:18px}
  }
  @container gl (max-width:560px){
    .gl-stat{padding:14px}
    .gl-stat b{font-size:22px;line-height:30px}
    .gl-head__actions{width:100%}
    .gl-head__actions .t-btn--primary{flex:1 1 100%;order:-1}
    .gl-head__actions .t-btn--secondary{flex:1}
    .gl-tabs{width:100%;overflow-x:auto;flex-wrap:nowrap}
    .gl-tab{flex:none}
  }
  @media (max-width:520px){.gl-two{grid-template-columns:1fr}.gl-head h1{font-size:22px;line-height:30px}}
  @media (prefers-reduced-motion:reduce){.gl *,.gl,.gl-dlg,.gl-dlg::backdrop{animation:none!important;transition:none!important}}
</style>
