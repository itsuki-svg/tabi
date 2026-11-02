<?php
require_once __DIR__ . '/auth.php';

// ログアウト
if (isset($_GET['logout'])) { auth_logout(); safe_redirect('login'); }

// 未ログインはlogin.phpへ
if (!auth_check()) { safe_redirect('login'); }

send_security_headers();

$me = auth_user();

$providers = [
  ['id'=>'claude','name'=>'Claude','model'=>'Sonnet 4', 'color'=>'#d4875a'],
  ['id'=>'gemini','name'=>'Gemini','model'=>'2.0 Flash','color'=>'#4a9eff'],
  ['id'=>'gpt',   'name'=>'GPT',   'model'=>'4o',       'color'=>'#19c37d'],
  ['id'=>'grok',  'name'=>'Grok',  'model'=>'3',        'color'=>'#a78bfa'],
];
$plan_tabs = [
  ['id'=>'hotels',      'icon'=>'ti-building',        'label'=>'ホテル'],
  ['id'=>'sightseeing', 'icon'=>'ti-map-2',           'label'=>'観光スポット'],
  ['id'=>'restaurants', 'icon'=>'ti-tools-kitchen-2', 'label'=>'グルメ'],
  ['id'=>'transport',   'icon'=>'ti-train',           'label'=>'交通'],
];
$quick_prompts = ['おすすめの旅先を教えて','ホテルを探して','観光ルートを作って','グルメスポットは？','交通手段を教えて','予算内で収まる？'];
?><!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tabi — 旅の相棒AI</title>
<link rel="icon" href="favicon.ico" type="image/x-icon">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--bg:#f5f4f0;--bg2:#fff;--bg3:#f0ede8;--bdr:rgba(0,0,0,.1);--bdr2:rgba(0,0,0,.16);--t1:#1a1a18;--t2:#6b6a65;--t3:#9e9d99;--a1:#5b63f0;--a2:#7c5cbf}
@media(prefers-color-scheme:dark){:root{--bg:#111110;--bg2:#1c1b19;--bg3:#242320;--bdr:rgba(255,255,255,.1);--bdr2:rgba(255,255,255,.18);--t1:#f0ede8;--t2:#9e9d99;--t3:#6b6a65}}
body{font-family:'Noto Sans JP',system-ui,sans-serif;background:var(--bg);color:var(--t1);height:100vh;overflow:hidden}

/* ── APP ── */
.page-app{display:flex;height:100vh;overflow:hidden}
.sb{width:220px;flex-shrink:0;background:var(--bg2);border-right:0.5px solid var(--bdr);display:flex;flex-direction:column}
.sb-top{padding:12px 12px 9px;border-bottom:0.5px solid var(--bdr);display:flex;align-items:center;gap:8px}
.sb-mark{width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#5b63f0,#7c5cbf);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.sb-name{font-size:13px;font-weight:600}.sb-sub{font-size:10px;color:var(--t3)}
.nb{margin:8px;padding:7px;border-radius:8px;border:0.5px dashed var(--bdr2);background:transparent;color:var(--t2);font-size:12px;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:5px;transition:all .15s}
.nb:hover{border-color:var(--a1);color:var(--a1)}
.sl{padding:2px 12px 5px;font-size:10px;font-weight:500;color:var(--t3);text-transform:uppercase;letter-spacing:.05em}
.hs{flex:1;overflow-y:auto;padding:0 6px 4px}
.hs::-webkit-scrollbar{width:3px}.hs::-webkit-scrollbar-thumb{background:var(--bdr2);border-radius:2px}
.hi{padding:7px 9px;border-radius:8px;cursor:pointer;margin-bottom:2px;border:0.5px solid transparent;position:relative}
.hi:hover{background:var(--bg3)}.hi.on{background:var(--bg2);border-color:var(--bdr2)}
.hi-t{font-size:12px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;padding-right:18px}
.hi-m{font-size:10px;color:var(--t3);margin-top:1px}
.hi-del{position:absolute;right:7px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--t3);font-size:12px;display:none;padding:2px}
.hi:hover .hi-del{display:block}.hi-del:hover{color:#dc2626}
.sb-foot{padding:8px;border-top:0.5px solid var(--bdr)}
.user-row{display:flex;align-items:center;gap:7px;padding:7px 8px;border-radius:8px;cursor:pointer;transition:all .15s}
.user-row:hover{background:var(--bg3)}
.user-av{width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,var(--a1),var(--a2));display:flex;align-items:center;justify-content:center;font-size:11px;color:#fff;flex-shrink:0;overflow:hidden}
.user-av img{width:100%;height:100%;object-fit:cover}
.user-name{font-size:12px;font-weight:500;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.main{flex:1;display:flex;flex-direction:column;overflow:hidden;min-width:0}
.hdr{padding:10px 14px;border-bottom:0.5px solid var(--bdr);display:flex;align-items:center;gap:8px;background:var(--bg2);flex-shrink:0}
.hdr-title{font-size:13px;font-weight:500;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.prov-wrap{position:relative}
.ppill{display:flex;align-items:center;gap:5px;padding:4px 9px;border-radius:20px;border:0.5px solid var(--bdr2);font-size:11px;color:var(--t2);background:var(--bg3);cursor:pointer;font-family:inherit}
.ppill:hover{border-color:var(--a1)}
.pdot{width:6px;height:6px;border-radius:50%}
.pmenu{display:none;position:absolute;top:calc(100% + 5px);right:0;background:var(--bg2);border:0.5px solid var(--bdr2);border-radius:10px;padding:5px;z-index:50;min-width:155px;box-shadow:0 4px 20px rgba(0,0,0,.12)}
.pmenu.open{display:block}
.pmrow{display:flex;align-items:center;gap:9px;padding:7px 10px;border-radius:7px;border:none;background:transparent;cursor:pointer;width:100%;text-align:left;font-family:inherit;color:var(--t1)}
.pmrow:hover{background:var(--bg3)}.pmrow.sel{background:rgba(91,99,240,.08)}
.pm-name{font-size:13px;font-weight:500}.pm-model{font-size:10px;color:var(--t3)}
.ico{width:27px;height:27px;border-radius:8px;border:0.5px solid var(--bdr);background:var(--bg3);display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--t2);font-size:13px;text-decoration:none;transition:all .15s}
.ico:hover{border-color:var(--a1);color:var(--a1)}
.plan-tabs{display:flex;border-bottom:0.5px solid var(--bdr);background:var(--bg2);flex-shrink:0;overflow-x:auto}
.plan-tabs::-webkit-scrollbar{display:none}
.ptab{display:flex;align-items:center;gap:5px;padding:8px 13px;font-size:12px;color:var(--t2);cursor:pointer;border-bottom:2px solid transparent;white-space:nowrap;transition:all .15s;font-family:inherit;background:none;border-top:none;border-left:none;border-right:none}
.ptab:hover{color:var(--t1)}.ptab.on{color:var(--t1);font-weight:500;border-bottom-color:var(--a1)}
.pcnt{display:inline-flex;align-items:center;justify-content:center;min-width:16px;height:16px;padding:0 4px;border-radius:8px;background:var(--bg3);font-size:10px;color:var(--t2)}
.ptab.on .pcnt{background:var(--a1);color:#fff}
.msgs-wrap{flex:1;overflow-y:auto;padding:14px 15px;display:flex;flex-direction:column;gap:10px}
.msgs-wrap::-webkit-scrollbar{width:4px}.msgs-wrap::-webkit-scrollbar-thumb{background:var(--bdr2);border-radius:2px}
.welcome{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100%;text-align:center;padding:24px 16px}
.wlc-icon{width:62px;height:62px;border-radius:50%;margin-bottom:14px;background:linear-gradient(135deg,#5b63f0,#7c5cbf);display:flex;align-items:center;justify-content:center}
.wlc-title{font-size:20px;font-weight:600;margin-bottom:6px}.wlc-sub{font-size:13px;color:var(--t2);line-height:1.7;max-width:300px;margin-bottom:22px}
.sug-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px;max-width:420px;width:100%}
.sug-btn{padding:10px 12px;border-radius:10px;border:0.5px solid var(--bdr2);background:var(--bg2);color:var(--t2);font-size:12px;cursor:pointer;text-align:left;line-height:1.4;font-family:inherit;transition:all .15s}
.sug-btn:hover{border-color:var(--a1);color:var(--t1)}
.mrow{display:flex;gap:7px;animation:fu .2s ease}
.mrow.usr{flex-direction:row-reverse}
@keyframes fu{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:translateY(0)}}
.av{width:27px;height:27px;border-radius:50%;flex-shrink:0;margin-top:2px;display:flex;align-items:center;justify-content:center;font-size:11px}
.av.ai{background:linear-gradient(135deg,#5b63f0,#7c5cbf)}.av.me{background:var(--bg3);border:0.5px solid var(--bdr2);color:var(--t2)}
.mbody{max-width:76%;min-width:0;display:flex;flex-direction:column;gap:4px}
.mlbl{font-size:10px;color:var(--t2);display:flex;align-items:center;gap:3px}
.ldot{width:5px;height:5px;border-radius:50%}
.bub{padding:9px 12px;font-size:13px;line-height:1.7;white-space:pre-wrap;word-break:break-word}
.bub.ai{border-radius:4px 12px 12px 12px;background:var(--bg2);border:0.5px solid var(--bdr)}
.bub.me{border-radius:12px 4px 12px 12px;background:linear-gradient(135deg,var(--a1),var(--a2));color:#fff}
.typing{display:flex;gap:4px;align-items:center;padding:3px 0}
.typing i{width:6px;height:6px;border-radius:50%;background:var(--t3);animation:bk 1.2s infinite}
.typing i:nth-child(2){animation-delay:.2s}.typing i:nth-child(3){animation-delay:.4s}
@keyframes bk{0%,100%{opacity:.2}50%{opacity:1}}
.cursor{display:inline-block;width:2px;height:13px;background:var(--a1);margin-left:2px;vertical-align:text-bottom;animation:bk .8s infinite}
.preview-card{border:0.5px solid var(--bdr2);border-radius:12px;overflow:hidden;background:var(--bg2);max-width:300px}
.pc-head{display:flex;align-items:center;padding:10px 12px;border-bottom:0.5px solid var(--bdr)}
.pc-badge{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:20px;font-size:10px;font-weight:500}
.pc-title{font-size:13px;font-weight:600;padding:10px 12px 4px}
.pc-body{font-size:12px;color:var(--t2);padding:0 12px 10px;line-height:1.65}
.pc-links{display:flex;gap:5px;padding:0 12px 10px;flex-wrap:wrap}
.pc-link{display:inline-flex;align-items:center;gap:3px;padding:3px 8px;border-radius:6px;border:0.5px solid var(--bdr2);font-size:11px;color:var(--t2);text-decoration:none;background:var(--bg3);transition:all .15s}
.pc-link:hover{border-color:var(--a1);color:var(--a1)}
.pc-foot{display:flex;gap:6px;padding:8px 12px;border-top:0.5px solid var(--bdr);background:var(--bg3)}
.pc-save{flex:1;padding:7px;border-radius:8px;border:none;background:var(--a1);color:#fff;font-size:12px;font-weight:500;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:4px;transition:opacity .15s}
.pc-save:hover{opacity:.88}.pc-save:disabled{background:var(--t3);cursor:default;opacity:1}
.pc-skip{padding:7px 10px;border-radius:8px;border:0.5px solid var(--bdr2);background:transparent;color:var(--t2);font-size:12px;cursor:pointer;font-family:inherit}
.plan-panel{display:none;flex-direction:column;gap:8px;padding:12px 14px;overflow-y:auto;flex:1}
.plan-panel.show{display:flex}.plan-panel::-webkit-scrollbar{width:4px}.plan-panel::-webkit-scrollbar-thumb{background:var(--bdr2);border-radius:2px}
.pcard{background:var(--bg2);border:0.5px solid var(--bdr);border-radius:12px;overflow:hidden}
.pcard-head{display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border-bottom:0.5px solid var(--bdr)}
.pcard-info{display:flex;align-items:center;gap:7px}.pcard-title{font-size:13px;font-weight:500}
.pcard-del{background:none;border:none;cursor:pointer;color:var(--t3);font-size:13px;padding:2px}.pcard-del:hover{color:#dc2626}
.pcard-body{padding:9px 12px;font-size:12px;color:var(--t2);line-height:1.6}
.empty-state{padding:32px 16px;text-align:center;color:var(--t3);font-size:12px;line-height:1.7}
/* 予約カード */
.res-add-btn{display:flex;align-items:center;justify-content:center;gap:6px;padding:8px;border:0.5px dashed var(--bdr2);border-radius:10px;background:transparent;color:var(--t2);font-size:12px;cursor:pointer;font-family:inherit;margin-top:4px}
.res-add-btn:hover{border-color:var(--a1);color:var(--a1)}
.day-label{font-size:10px;font-weight:500;color:var(--t3);text-transform:uppercase;letter-spacing:.05em;padding:6px 0 5px;border-bottom:0.5px solid var(--bdr);margin-bottom:7px}
.res-card{display:flex;gap:10px;padding:9px 11px;border:0.5px solid var(--bdr);border-radius:10px;background:var(--bg2);margin-bottom:6px;position:relative}
.res-time{display:flex;flex-direction:column;align-items:center;min-width:40px;padding-top:2px;flex-shrink:0}
.res-time-main{font-size:13px;font-weight:500;color:var(--t1)}
.res-time-sub{font-size:9px;color:var(--t3)}
.res-divider{width:0.5px;background:var(--bdr);flex-shrink:0}
.res-body{flex:1;min-width:0}
.res-name{font-size:13px;font-weight:500;color:var(--t1);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.res-note{font-size:11px;color:var(--t2);margin-top:2px;line-height:1.5}
.res-url{display:inline-flex;align-items:center;gap:3px;font-size:11px;color:var(--a1);text-decoration:none;margin-top:3px}
.res-url:hover{text-decoration:underline}
.res-actions{display:flex;gap:4px;flex-shrink:0;padding-top:1px}
.res-btn{background:none;border:none;cursor:pointer;color:var(--t3);font-size:13px;padding:2px}
.res-btn:hover{color:var(--a1)}
.res-btn.del:hover{color:#dc2626}
/* 予約モーダル */
.res-modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:200;align-items:center;justify-content:center}
.res-modal-bg.on{display:flex}
.res-modal{background:var(--bg2);border:0.5px solid var(--bdr2);border-radius:14px;padding:22px;width:420px;max-width:94vw;max-height:90vh;overflow-y:auto}
.res-modal h2{font-size:15px;font-weight:600;margin-bottom:3px}
.res-modal-sub{font-size:12px;color:var(--t3);margin-bottom:16px}
.rfield{margin-bottom:11px}
.rlabel{display:block;font-size:11px;font-weight:500;color:var(--t2);margin-bottom:4px}
.rinput{width:100%;padding:8px 10px;background:var(--bg3);border:0.5px solid var(--bdr2);border-radius:8px;color:var(--t1);font-size:13px;font-family:inherit;outline:none}
.rinput:focus{border-color:var(--a1)}
.rinput-url{color:var(--a1)}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.res-foot{display:flex;gap:8px;margin-top:14px}
.res-foot button{flex:1;padding:9px;border-radius:9px;font-size:13px;font-weight:500;cursor:pointer;font-family:inherit}
.res-foot .btn-save{background:var(--a1);color:#fff;border:none}
.res-foot .btn-save:hover{opacity:.9}
.res-foot .btn-cancel{background:transparent;color:var(--t2);border:0.5px solid var(--bdr2)}
.pc-res-btn{flex:1;padding:7px;border-radius:8px;border:0.5px solid var(--bdr2);background:transparent;color:var(--t2);font-size:12px;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:4px}
.pc-res-btn:hover{border-color:var(--a1);color:var(--a1)}
.qbar{display:none;gap:5px;padding:6px 13px;overflow-x:auto;border-top:0.5px solid var(--bdr);flex-shrink:0}
.qbar.on{display:flex}.qbar::-webkit-scrollbar{display:none}
.qb{flex-shrink:0;padding:4px 10px;border-radius:20px;border:0.5px solid var(--bdr);background:var(--bg2);color:var(--t2);font-size:11px;cursor:pointer;white-space:nowrap;font-family:inherit}
.qb:hover{border-color:var(--a1);color:var(--t1)}
.inp-area{padding:8px 13px 14px;flex-shrink:0}
.inp-box{display:flex;align-items:center;gap:6px;background:var(--bg3);border:0.5px solid var(--bdr2);border-radius:12px;padding:8px 8px 8px 13px;transition:border-color .2s}
.inp-box:focus-within{border-color:var(--a1)}
.chat-inp{flex:1;background:none;border:none;outline:none;font-size:13px;color:var(--t1);font-family:inherit;min-height:20px;max-height:120px;overflow-y:auto;word-break:break-word;cursor:text}
.chat-inp:empty::before{content:attr(data-placeholder);color:var(--t3);pointer-events:none}
.send{width:29px;height:29px;border-radius:50%;border:none;flex-shrink:0;background:linear-gradient(135deg,var(--a1),var(--a2));cursor:pointer;display:flex;align-items:center;justify-content:center}
.send:disabled{background:var(--bdr2);opacity:.5;cursor:default}
.ih{text-align:center;font-size:10px;color:var(--t3);margin-top:5px}
/* modal */
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:100;align-items:center;justify-content:center}
.modal-bg.on{display:flex}
.modal-box{background:var(--bg2);border:0.5px solid var(--bdr2);border-radius:14px;padding:24px;width:430px;max-width:94vw;max-height:90vh;overflow-y:auto}
.modal-box h2{font-size:15px;font-weight:600;margin-bottom:3px}
.modal-sub{font-size:12px;color:var(--t3);margin-bottom:18px}
.mfield{margin-bottom:12px}
.mlabel{display:block;font-size:12px;font-weight:500;color:var(--t2);margin-bottom:5px;display:flex;align-items:center;gap:5px}
.minput{width:100%;padding:8px 10px;background:var(--bg3);border:0.5px solid var(--bdr2);border-radius:7px;color:var(--t1);font-size:13px;font-family:inherit;outline:none}
.minput:focus{border-color:var(--a1)}
.minput.mono{font-family:monospace}
.msave{width:100%;padding:10px;border:none;border-radius:9px;background:linear-gradient(135deg,var(--a1),var(--a2));color:#fff;font-size:13px;font-weight:500;cursor:pointer;font-family:inherit;margin-top:4px}
.msave:hover{opacity:.9}
.modal-note{background:var(--bg3);border-radius:7px;padding:8px 10px;font-size:11px;color:var(--t3);line-height:1.6;margin-bottom:14px}
.mtabs{display:flex;background:var(--bg3);border-radius:8px;padding:3px;margin-bottom:16px}
.mtab{flex:1;padding:6px;border:none;border-radius:6px;background:transparent;color:var(--t2);font-size:12px;cursor:pointer;font-family:inherit}
.mtab.on{background:var(--bg2);color:var(--t1);font-weight:500}
.msec{display:none}.msec.on{display:block}
.mdivider{height:0.5px;background:var(--bdr);margin:16px 0}
.mdanger{padding:9px 10px;background:rgba(220,38,38,.06);border:0.5px solid rgba(220,38,38,.2);border-radius:7px;font-size:12px;color:#dc2626;margin-bottom:8px}
.key-saved{font-size:11px;color:#16a34a;margin-left:6px}
</style>
</head>
<body>

<!-- ═══ APP ═══ -->
<div class="page-app">
  <aside class="sb">
    <div class="sb-top">
      <div class="sb-mark"><i class="ti ti-plane" style="color:white;font-size:13px"></i></div>
      <div><div class="sb-name">Tabi</div><div class="sb-sub">旅の相棒AI</div></div>
    </div>
    <button class="nb" id="newBtn"><i class="ti ti-plus" style="font-size:12px"></i> 新しい会話</button>
    <div class="sl">最近の会話</div>
    <div class="hs" id="histList"><div style="padding:12px 8px;text-align:center;color:var(--t3);font-size:11px">読み込み中...</div></div>
    <div class="sb-foot">
      <div class="user-row" id="userRowBtn">
        <div class="user-av" id="userAv">
          <?php if (!empty($me['avatar_url'])): ?>
          <img src="<?= htmlspecialchars($me['avatar_url']) ?>" alt="">
          <?php else: ?>
          <i class="ti ti-user" style="font-size:12px"></i>
          <?php endif; ?>
        </div>
        <div class="user-name"><?= htmlspecialchars($me['name'] ?: $me['email']) ?></div>
        <i class="ti ti-settings" style="font-size:13px;color:var(--t3)"></i>
      </div>
      <a href="index.php?logout=1"
         onclick="return confirm('ログアウトしますか？')"
         style="display:flex;align-items:center;justify-content:center;gap:5px;
                width:100%;padding:7px;border-radius:8px;border:0.5px solid var(--bdr);
                background:transparent;color:var(--t3);font-size:12px;
                text-decoration:none;transition:all .15s;margin-top:4px"
         onmouseover="this.style.color='#dc2626';this.style.borderColor='#dc2626'"
         onmouseout="this.style.color='var(--t3)';this.style.borderColor='var(--bdr)'">
        <i class="ti ti-logout" style="font-size:13px"></i> ログアウト
      </a>
    </div>
  </aside>

  <div class="main">
    <header class="hdr">
      <div class="hdr-title" id="hdrTitle">新しい会話</div>
      <div class="prov-wrap">
        <button class="ppill" id="provBtn">
          <span class="pdot" id="provDot" style="background:#d4875a"></span>
          <span id="provName">Claude</span>
          <i class="ti ti-chevron-down" style="font-size:10px"></i>
        </button>
        <div class="pmenu" id="provMenu">
          <?php foreach($providers as $p): ?>
          <button class="pmrow" data-id="<?=$p['id']?>" data-color="<?=$p['color']?>">
            <span class="pdot" style="background:<?=$p['color']?>"></span>
            <div><div class="pm-name" style="color:<?=$p['color']?>"><?=$p['name']?></div><div class="pm-model"><?=$p['model']?></div></div>
            <span class="pm-chk" style="color:<?=$p['color']?>;margin-left:auto;display:none">✓</span>
          </button>
          <?php endforeach; ?>
        </div>
      </div>
      <button class="ico" id="settingsBtn" title="設定"><i class="ti ti-settings" style="font-size:12px"></i></button>
    </header>

    <div class="plan-tabs">
      <button class="ptab on" data-tab="chat"><i class="ti ti-message" style="font-size:12px"></i> チャット</button>
      <?php foreach($plan_tabs as $t): ?>
      <button class="ptab" data-tab="<?=$t['id']?>">
        <i class="ti <?=$t['icon']?>" style="font-size:12px"></i> <?=htmlspecialchars($t['label'])?>
        <span class="pcnt" id="cnt_<?=$t['id']?>">0</span>
      </button>
      <?php endforeach; ?>
      <button class="ptab" data-tab="reservations">
        <i class="ti ti-calendar-event" style="font-size:12px"></i> 予約
        <span class="pcnt" id="cnt_reservations">0</span>
      </button>
    </div>

    <div id="chatArea" style="display:flex;flex-direction:column;flex:1;overflow:hidden">
      <div class="msgs-wrap" id="msgsWrap">
        <div class="welcome" id="welcome">
          <div class="wlc-icon"><i class="ti ti-plane" style="color:white;font-size:26px"></i></div>
          <div class="wlc-title">旅の相棒、Tabiです</div>
          <p class="wlc-sub">ホテル・観光・グルメ・交通まで、会話しながら一緒に旅をプランニングしましょう！</p>
          <div class="sug-grid">
            <?php foreach($quick_prompts as $s): ?>
            <button class="sug-btn" onclick="send(<?=json_encode($s)?>)"><?=htmlspecialchars($s)?></button>
            <?php endforeach; ?>
          </div>
        </div>
        <div id="msgs" style="display:none;flex-direction:column;gap:10px"></div>
        <div id="bottom"></div>
      </div>
      <div class="qbar" id="qbar">
        <?php foreach($quick_prompts as $q): ?>
        <button class="qb" onclick="send(<?=json_encode($q)?>)"><?=htmlspecialchars($q)?></button>
        <?php endforeach; ?>
      </div>
      <div class="inp-area">
        <div class="inp-box">
          <div class="chat-inp" id="inp" contenteditable="true" role="textbox" aria-label="メッセージ入力" spellcheck="false" data-placeholder="Tabiに話しかける..."></div>
          <button class="send" id="sendBtn" disabled>
            <i class="ti ti-send" style="color:white;font-size:13px"></i>
          </button>
        </div>
        <div class="ih" id="hint">Claude · Sonnet 4 が応答します</div>
      </div>
    </div>

    <?php foreach($plan_tabs as $t): ?>
    <div class="plan-panel" id="panel_<?=$t['id']?>">
      <div class="empty-state" id="empty_<?=$t['id']?>">
        <i class="ti <?=$t['icon']?>" style="font-size:28px;display:block;margin-bottom:8px;opacity:.4"></i>
        チャットでTabiが提案した内容の<br>「プランに追加」を押すとここに表示されます
      </div>
    </div>
    <?php endforeach; ?>

    <!-- 予約パネル -->
    <div class="plan-panel" id="panel_reservations">
      <div id="res-list-wrap"></div>
      <div class="empty-state" id="empty_reservations">
        <i class="ti ti-calendar-event" style="font-size:28px;display:block;margin-bottom:8px;opacity:.4"></i>
        チャット内のカードから「予約に登録」を押すか<br>下のボタンから手動で追加できます
      </div>
      <button class="res-add-btn" id="resAddBtn">
        <i class="ti ti-plus" style="font-size:13px"></i> 予約を手動で追加
      </button>
    </div>
  </div>
</div>

<!-- 予約登録モーダル -->
<div class="res-modal-bg" id="resModal">
  <div class="res-modal">
    <h2 id="resModalTitle">予約を登録</h2>
    <div class="res-modal-sub">日時・詳細を入力してください</div>
    <input type="hidden" id="resEditId">
    <div class="rfield">
      <label class="rlabel">種類</label>
      <select class="rinput" id="resType">
        <option value="hotels">ホテル（チェックイン / アウト）</option>
        <option value="restaurants">グルメ（予約時間）</option>
        <option value="sightseeing">観光スポット</option>
        <option value="transport">交通</option>
        <option value="other">その他</option>
      </select>
    </div>
    <div class="rfield">
      <label class="rlabel">名前 <span style="color:#dc2626">*</span></label>
      <input class="rinput" type="text" id="resTitle" placeholder="施設・店舗名" autocomplete="new-password">
    </div>
    <div class="rfield">
      <label class="rlabel">場所・住所</label>
      <input class="rinput" type="text" id="resLocation" placeholder="例: 京都府京都市右京区嵯峨" autocomplete="new-password">
    </div>
    <div class="rfield">
      <label class="rlabel">URL（予約サイト・公式サイト）</label>
      <input class="rinput rinput-url" type="url" id="resUrl" placeholder="https://...">
    </div>
    <div class="row2">
      <div class="rfield">
        <label class="rlabel">開始日</label>
        <input class="rinput" type="date" id="resStartDate">
      </div>
      <div class="rfield">
        <label class="rlabel">開始時間</label>
        <input class="rinput" type="time" id="resStartTime">
      </div>
    </div>
    <div class="row2">
      <div class="rfield">
        <label class="rlabel">終了日（任意）</label>
        <input class="rinput" type="date" id="resEndDate">
      </div>
      <div class="rfield">
        <label class="rlabel">終了時間（任意）</label>
        <input class="rinput" type="time" id="resEndTime">
      </div>
    </div>
    <div class="rfield">
      <label class="rlabel">メモ（予約番号・人数など）</label>
      <textarea class="rinput" id="resMemo" rows="3" placeholder="予約番号: AB1234&#10;2名　など" style="resize:vertical"></textarea>
    </div>
    <div id="res-modal-err" style="font-size:12px;color:#dc2626;margin-bottom:8px;display:none"></div>
    <div class="res-foot">
      <button class="btn-cancel" onclick="closeResModal()">キャンセル</button>
      <button class="btn-save" id="resSaveBtn">登録する</button>
    </div>
  </div>
</div>

<!-- Settings Modal -->
<div class="modal-bg" id="settingsModal">
  <div class="modal-box">
    <h2>設定</h2>
    <div class="modal-sub"><?= htmlspecialchars($me['email']) ?></div>

    <div class="mtabs">
      <button class="mtab on" data-sec="apikeys">APIキー</button>
      <button class="mtab" data-sec="profile">プロフィール</button>
      <button class="mtab" data-sec="account">アカウント</button>
    </div>

    <!-- APIキー -->
    <div class="msec on" id="sec-apikeys">
      <div class="modal-note">💡 入力したAPIキーはサーバーに暗号化して保存されます。どの端末からでも利用できます。</div>
      <?php foreach($providers as $p): ?>
      <div class="mfield">
        <label class="mlabel">
          <span class="pdot" style="background:<?=$p['color']?>"></span>
          <span style="color:<?=$p['color']?>"><?=$p['name']?> API Key</span>
          <span class="key-saved" id="saved_<?=$p['id']?>" style="display:none">✓ 保存済み</span>
        </label>
        <div style="position:relative">
          <input class="minput mono" type="text" id="key_<?=$p['id']?>"
                 placeholder="未入力（保存済みキーを使用）"
                 autocomplete="new-password"
                 style="padding-right:36px">
          <button type="button" onclick="toggleKeyVis('key_<?=$p['id']?>',this)"
                  style="position:absolute;right:8px;top:50%;transform:translateY(-50%);
                         background:none;border:none;cursor:pointer;color:var(--t3);font-size:14px"
                  title="表示/非表示">👁</button>
        </div>
      </div>
      <?php endforeach; ?>
      <button class="msave" id="saveKeysBtn">APIキーを保存</button>
    </div>

    <!-- プロフィール -->
    <div class="msec" id="sec-profile">
      <div class="mfield">
        <label class="mlabel">名前</label>
        <input class="minput" type="text" id="profileName" value="<?= htmlspecialchars($me['name']) ?>">
      </div>
      <button class="msave" id="saveProfileBtn">名前を更新</button>
      <?php if (empty($me['google_id'])): ?>
      <div class="mdivider"></div>
      <div class="mfield">
        <label class="mlabel">現在のパスワード</label>
        <input class="minput" type="password" id="currentPw" placeholder="現在のパスワード">
      </div>
      <div class="mfield">
        <label class="mlabel">新しいパスワード（8文字以上）</label>
        <input class="minput" type="password" id="newPw" placeholder="新しいパスワード">
      </div>
      <button class="msave" id="changePwBtn">パスワードを変更</button>
      <?php endif; ?>
    </div>

    <!-- アカウント -->
    <div class="msec" id="sec-account">
      <div class="modal-note">ログアウトするとセッションが終了します。APIキーや会話履歴はサーバーに保存されます。</div>
      <button class="msave" style="background:var(--t2)" onclick="if(confirm('ログアウトしますか？'))location.href='index.php?logout=1'">
        <i class="ti ti-logout" style="font-size:13px"></i> ログアウト
      </button>
    </div>

    <div id="modal-msg" style="margin-top:10px;font-size:12px;text-align:center;display:none"></div>
  </div>
</div>

<script>
(function(){
var PROVS={claude:{name:'Claude',model:'Sonnet 4',color:'#d4875a'},gemini:{name:'Gemini',model:'2.0 Flash',color:'#4a9eff'},gpt:{name:'GPT',model:'4o',color:'#19c37d'},grok:{name:'Grok',model:'3',color:'#a78bfa'}};
var BADGE={hotels:{style:'background:#faeeda;color:#854f0b',label:'ホテル候補',icon:'ti-building'},sightseeing:{style:'background:#e6f1fb;color:#185fa5',label:'観光スポット候補',icon:'ti-map-2'},restaurants:{style:'background:#eaf3de;color:#3b6d11',label:'グルメ候補',icon:'ti-tools-kitchen-2'},transport:{style:'background:#eeedfe;color:#534ab7',label:'交通情報',icon:'ti-train'}};

// CSRFトークン（PHPから埋め込む）
var CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;

// APIリクエスト共通ヘッダー
function apiFetch(url, opts){
  opts = opts || {};
  opts.headers = Object.assign({'Content-Type':'application/json','X-CSRF-Token':CSRF_TOKEN}, opts.headers||{});
  return fetch(url, opts);
}

var history=[],provider='claude',busy=false,convId=newId(),convTitle='';
var planItems={hotels:[],sightseeing:[],restaurants:[],transport:[]};

var inp=ge('inp'),sendBtn=ge('sendBtn'),welcome=ge('welcome'),msgs=ge('msgs');
var qbar=ge('qbar'),hint=ge('hint'),provBtn=ge('provBtn'),provDot=ge('provDot');
var provName=ge('provName'),provMenu=ge('provMenu'),chatArea=ge('chatArea');
var histList=ge('histList'),hdrTitle=ge('hdrTitle');
var settingsModal=ge('settingsModal');

function ge(id){return document.getElementById(id);}
function newId(){return 'c'+Date.now().toString(36)+Math.random().toString(36).slice(2,6);}
function esc(s){var d=document.createElement('div');d.textContent=s;return d.innerHTML;}
function toggleKeyVis(id,btn){
  var el=ge(id);
  if(!el)return;
  if(el.type==='text'){el.type='password';btn.textContent='👁';}
  else{el.type='text';btn.textContent='🙈';}
}

/* provider */
function setProvider(id){
  provider=id; var p=PROVS[id];
  provDot.style.background=p.color; provName.textContent=p.name;
  hint.textContent=p.name+' · '+p.model+' が応答します';
  document.querySelectorAll('.pmrow').forEach(function(b){var s=b.dataset.id===id;b.classList.toggle('sel',s);b.querySelector('.pm-chk').style.display=s?'inline':'none';});
  provMenu.classList.remove('open');
}
setProvider('claude');
provBtn.addEventListener('click',function(e){e.stopPropagation();provMenu.classList.toggle('open');});
document.querySelectorAll('.pmrow').forEach(function(b){b.addEventListener('click',function(){setProvider(b.dataset.id);});});
document.addEventListener('click',function(){provMenu.classList.remove('open');});

/* plan tabs */
document.querySelectorAll('.ptab').forEach(function(tab){
  tab.addEventListener('click',function(){
    document.querySelectorAll('.ptab').forEach(function(t){t.classList.remove('on');});
    tab.classList.add('on');
    var t=tab.dataset.tab;
    chatArea.style.display=(t==='chat')?'flex':'none';
    document.querySelectorAll('.plan-panel').forEach(function(p){p.classList.remove('show');});
    if(t!=='chat'){var p=ge('panel_'+t);if(p)p.classList.add('show');}
    if(t==='reservations') loadReservations();
  });
});

/* settings modal */
ge('settingsBtn').addEventListener('click',function(){settingsModal.classList.add('on');loadSavedKeys();});
ge('userRowBtn').addEventListener('click',function(){settingsModal.classList.add('on');loadSavedKeys();});
settingsModal.addEventListener('click',function(e){if(e.target===settingsModal)settingsModal.classList.remove('on');});

document.querySelectorAll('.mtab').forEach(function(tab){
  tab.addEventListener('click',function(){
    document.querySelectorAll('.mtab').forEach(function(t){t.classList.remove('on');});
    tab.classList.add('on');
    document.querySelectorAll('.msec').forEach(function(s){s.classList.remove('on');});
    var s=ge('sec-'+tab.dataset.sec); if(s) s.classList.add('on');
  });
});

function showModalMsg(msg,ok){
  var el=ge('modal-msg');
  el.textContent=msg; el.style.color=ok?'#16a34a':'#dc2626'; el.style.display='block';
  setTimeout(function(){el.style.display='none';},3000);
}

/* APIキー */
function loadSavedKeys(){
  apiFetch('api/user.php?action=get_keys').then(function(r){return r.json();}).then(function(d){
    if(!d.ok) return;
    ['claude','gemini','gpt','grok'].forEach(function(k){
      var saved=ge('saved_'+k);
      if(d.keys[k]==='***saved***'){
        if(saved) saved.style.display='inline';
        var el=ge('key_'+k); if(el) el.placeholder='●●●●●●●●（保存済み）';
      } else {
        if(saved) saved.style.display='none';
      }
    });
  });
}

ge('saveKeysBtn').addEventListener('click',function(){
  var keys={};
  ['claude','gemini','gpt','grok'].forEach(function(k){ keys[k]=ge('key_'+k).value.trim(); });
  apiFetch('api/user.php?action=save_keys',{method:'POST',body:JSON.stringify({keys:keys})})
    .then(function(r){return r.json();}).then(function(d){
      if(d.ok){showModalMsg('APIキーを保存しました',true);loadSavedKeys();['claude','gemini','gpt','grok'].forEach(function(k){var el=ge('key_'+k);if(el)el.value='';});}
      else showModalMsg(d.error||'保存に失敗しました',false);
    });
});

ge('saveProfileBtn').addEventListener('click',function(){
  var name=ge('profileName').value.trim();
  apiFetch('api/user.php?action=update_profile',{method:'POST',body:JSON.stringify({name:name})})
    .then(function(r){return r.json();}).then(function(d){
      if(d.ok) showModalMsg('名前を更新しました',true);
      else showModalMsg(d.error||'更新に失敗しました',false);
    });
});

var changePwBtn=ge('changePwBtn');
if(changePwBtn){
  changePwBtn.addEventListener('click',function(){
    var cur=ge('currentPw').value, nw=ge('newPw').value;
    apiFetch('api/user.php?action=change_password',{method:'POST',body:JSON.stringify({current:cur,new:nw})})
      .then(function(r){return r.json();}).then(function(d){
        if(d.ok){showModalMsg('パスワードを変更しました',true);ge('currentPw').value='';ge('newPw').value='';}
        else showModalMsg(d.error||'変更に失敗しました',false);
      });
  });
}

/* new conv */
ge('newBtn').addEventListener('click',newConv);
function newConv(){
  convId=newId();convTitle='';history=[];busy=false;
  msgs.innerHTML='';msgs.style.display='none';welcome.style.display='';
  qbar.classList.remove('on');hdrTitle.textContent='新しい会話';
  planItems={hotels:[],sightseeing:[],restaurants:[],transport:[]};
  ['hotels','sightseeing','restaurants','transport'].forEach(function(t){
    var p=ge('panel_'+t);if(p)Array.from(p.querySelectorAll('.pcard')).forEach(function(c){c.remove();});
    var e=ge('empty_'+t);if(e)e.style.display='';
  });
  updateCounts();
  document.querySelectorAll('.ptab').forEach(function(t){t.classList.remove('on');});
  document.querySelector('.ptab[data-tab="chat"]').classList.add('on');
  chatArea.style.display='flex';
  document.querySelectorAll('.plan-panel').forEach(function(p){p.classList.remove('show');});
  document.querySelectorAll('.hi').forEach(function(el){el.classList.remove('on');});
  updateBtn();inp.focus();
}

/* history */
function loadHistory(){
  apiFetch('api/history.php?action=list').then(function(r){return r.json();}).then(function(list){
    if(!Array.isArray(list)||!list.length){histList.innerHTML='<div style="padding:12px 8px;text-align:center;color:var(--t3);font-size:11px">まだ会話がありません</div>';return;}
    histList.innerHTML='';
    list.forEach(function(item){histList.appendChild(makeHi(item));});
  }).catch(function(){histList.innerHTML='';});
}

function makeHi(item){
  var el=document.createElement('div');el.className='hi'+(item.id===convId?' on':'');el.dataset.id=item.id;
  var d=new Date(item.updated_at*1000);
  var ds=(d.getMonth()+1)+'/'+d.getDate()+' '+d.getHours()+':'+String(d.getMinutes()).padStart(2,'0');
  el.innerHTML='<div class="hi-t">'+esc(item.title)+'</div><div class="hi-m">'+ds+' · '+item.cnt+'件</div><button class="hi-del" title="削除"><i class="ti ti-x" style="font-size:11px"></i></button>';
  el.addEventListener('click',function(e){if(e.target.closest('.hi-del'))return;loadConv(item.id);});
  el.querySelector('.hi-del').addEventListener('click',function(e){
    e.stopPropagation();if(!confirm('削除しますか？'))return;
    apiFetch('api/history.php?action=delete',{method:'POST',body:JSON.stringify({id:item.id})})
      .then(function(){if(convId===item.id)newConv();loadHistory();});
  });
  return el;
}

function loadConv(id){
  apiFetch('api/history.php?action=get&id='+id).then(function(r){return r.json();}).then(function(data){
    convId=data.id;convTitle=data.title;history=data.messages||[];
    setProvider(data.provider||'claude');
    msgs.innerHTML='';
    if(history.length){msgs.style.display='flex';welcome.style.display='none';}
    else{msgs.style.display='none';welcome.style.display='';}
    hdrTitle.textContent=data.title;
    history.forEach(function(m){addBubble(m.role==='user'?'user':'tabi',m.content,null,true);});
    qbar.classList.add('on');
    document.querySelectorAll('.hi').forEach(function(el){el.classList.toggle('on',el.dataset.id===id);});
    document.querySelectorAll('.ptab').forEach(function(t){t.classList.remove('on');});
    document.querySelector('.ptab[data-tab="chat"]').classList.add('on');
    chatArea.style.display='flex';
    document.querySelectorAll('.plan-panel').forEach(function(p){p.classList.remove('show');});
    loadPlanItems();scrollDown();
  });
}
loadHistory();

// リロード時に最後の会話を自動復元
apiFetch('api/history.php?action=list').then(function(r){return r.json();}).then(function(list){
  if(Array.isArray(list)&&list.length) loadConv(list[0].id);
}).catch(function(){});
function addPlanItem(type,title,body){
  apiFetch('api/plan.php?action=save',{method:'POST',body:JSON.stringify({conv_id:convId,type:type,title:title,body:body})})
    .then(function(r){return r.json();}).then(function(res){
      if(!res.ok)return;
      var item={id:res.id,type:type,title:title,body:body};
      planItems[type].push(item);renderPlanCard(item);updateCounts();
    });
}

function renderPlanCard(item){
  var panel=ge('panel_'+item.type); var empty=ge('empty_'+item.type);
  if(empty)empty.style.display='none';
  var b=BADGE[item.type]||{style:'',label:'',icon:'ti-star'};
  var div=document.createElement('div');div.className='pcard';div.dataset.planId=item.id;
  div.innerHTML='<div class="pcard-head"><div class="pcard-info"><span class="pc-badge" style="'+b.style+'"><i class="ti '+b.icon+'" style="font-size:11px"></i> '+b.label+'</span><div class="pcard-title">'+esc(item.title)+'</div></div><button class="pcard-del" title="削除"><i class="ti ti-trash" style="font-size:13px"></i></button></div><div class="pcard-body">'+esc(item.body)+'</div>';
  div.querySelector('.pcard-del').addEventListener('click',function(){
    if(!confirm('削除しますか？'))return;
    apiFetch('api/plan.php?action=delete',{method:'POST',body:JSON.stringify({id:item.id})});
    planItems[item.type]=planItems[item.type].filter(function(x){return x.id!==item.id;});
    div.remove();updateCounts();
    if(!planItems[item.type].length&&empty)empty.style.display='';
  });
  panel.appendChild(div);
}

function updateCounts(){
  ['hotels','sightseeing','restaurants','transport'].forEach(function(t){
    var el=ge('cnt_'+t);if(el)el.textContent=planItems[t].length;
    var tab=document.querySelector('.ptab[data-tab="'+t+'"]');
    if(tab){var c=tab.querySelector('.pcnt');if(c){c.style.background=planItems[t].length?'var(--a1)':'var(--bg3)';c.style.color=planItems[t].length?'#fff':'var(--t2)';}}
  });
}

function loadPlanItems(){
  apiFetch('api/plan.php?action=list&conv_id='+convId).then(function(r){return r.json();}).then(function(list){
    planItems={hotels:[],sightseeing:[],restaurants:[],transport:[]};
    ['hotels','sightseeing','restaurants','transport'].forEach(function(t){
      var p=ge('panel_'+t);if(!p)return;
      Array.from(p.querySelectorAll('.pcard')).forEach(function(c){c.remove();});
      var e=ge('empty_'+t);if(e)e.style.display='';
    });
    list.forEach(function(item){if(planItems[item.type])planItems[item.type].push(item);renderPlanCard(item);});
    updateCounts();
  });
}

/* card helpers */
function parseCard(text){var m=text.match(/```card\s*\n([\s\S]*?)\n?```/);if(!m)return null;try{return JSON.parse(m[1]);}catch(e){return null;}}
function stripCard(text){return text.replace(/```card\s*\n[\s\S]*?\n?```/g,'').trim();}

/* input */
inp.addEventListener('input',updateBtn);
inp.addEventListener('keydown',function(e){if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();trySend();}});
sendBtn.addEventListener('click',trySend);
function updateBtn(){sendBtn.disabled=busy||!inp.textContent.trim();}
function setBusy(v){busy=v;updateBtn();if(!v)qbar.classList.add('on');}
function trySend(){var t=inp.textContent.trim();if(!t||busy)return;inp.textContent='';updateBtn();send(t);}

/* send */
function send(text){
  if(busy)return;
  if(!history.length){convTitle=text.slice(0,40);hdrTitle.textContent=convTitle;}
  welcome.style.display='none';msgs.style.display='flex';
  history.push({role:'user',content:text});
  addBubble('user',text);
  var bid='b'+Date.now();addBubble('tabi','',bid);setBusy(true);
  apiFetch('api/chat.php',{method:'POST',
    body:JSON.stringify({messages:history.slice(-20),provider:provider,convId:convId,convTitle:convTitle,category:'general'})
  }).then(function(res){
    if(!res.ok){return res.json().catch(function(){return{error:'APIエラー'};}).then(function(e){updateBubble(bid,'⚠️ '+(e.error||'エラー'));setBusy(false);});}
    var reader=res.body.getReader(),dec=new TextDecoder(),buf='',acc='';
    function pump(){
      return reader.read().then(function(r){
        if(r.done){var card=parseCard(acc);var clean=card?stripCard(acc):acc;updateBubble(bid,clean,null,card);history.push({role:'assistant',content:acc});setBusy(false);loadHistory();scrollDown();return;}
        buf+=dec.decode(r.value,{stream:true});
        var idx;
        while((idx=buf.indexOf('\n\n'))!==-1){var chunk=buf.slice(0,idx).trim();buf=buf.slice(idx+2);if(!chunk.startsWith('data: '))continue;var raw=chunk.slice(6);if(raw==='[DONE]')continue;try{var o=JSON.parse(raw);if(o.error){acc='⚠️ '+o.error;break;}if(o.text){acc+=o.text;updateBubble(bid,stripCard(acc),true);}}catch(e){}}
        return pump();
      });
    }
    return pump();
  }).catch(function(e){updateBubble(bid,'⚠️ 接続エラー: '+e.message);setBusy(false);});
}

/* DOM helpers */
function addBubble(role,text,id,isHistory){
  var isUser=role==='user';
  var row=document.createElement('div');row.className='mrow'+(isUser?' usr':'');
  var av=document.createElement('div');av.className='av '+(isUser?'me':'ai');
  av.innerHTML=isUser?'<i class="ti ti-user" style="font-size:11px"></i>':'<i class="ti ti-plane" style="color:white;font-size:11px"></i>';
  var body=document.createElement('div');body.className='mbody';
  if(!isUser){var lbl=document.createElement('div');lbl.className='mlbl';lbl.style.color=PROVS[provider].color;lbl.innerHTML='<span class="ldot" style="background:'+PROVS[provider].color+'"></span> Tabi · '+PROVS[provider].name;body.appendChild(lbl);}
  var bub=document.createElement('div');bub.className='bub '+(isUser?'me':'ai');
  if(id)bub.id=id;
  var displayText=stripCard(text);
  if(displayText===''&&!isUser){bub.innerHTML='<div class="typing"><i></i><i></i><i></i></div>';}
  else{bub.textContent=displayText;}
  body.appendChild(bub);
  if(!isUser&&text&&!isHistory){var card=parseCard(text);if(card)body.appendChild(makePreviewCard(card));}
  row.appendChild(isUser?body:av);row.appendChild(isUser?av:body);
  msgs.appendChild(row);scrollDown();
}

function updateBubble(id,text,cursor,card){
  var el=ge(id);if(!el)return;
  el.innerHTML='';el.appendChild(document.createTextNode(stripCard(text)));
  if(cursor){var c=document.createElement('span');c.className='cursor';el.appendChild(c);}
  if(card&&!cursor){var body=el.parentNode;var old=body.querySelector('.preview-card');if(old)old.remove();body.appendChild(makePreviewCard(card));}
  scrollDown();
}

function makePreviewCard(card){
  var b=BADGE[card.type]||{style:'background:#f0ede8;color:#6b6a65',label:'情報',icon:'ti-info-circle'};
  var wrap=document.createElement('div');wrap.className='preview-card';
  var head=document.createElement('div');head.className='pc-head';
  head.innerHTML='<span class="pc-badge" style="'+b.style+'"><i class="ti '+b.icon+'" style="font-size:11px"></i> '+b.label+'</span>';
  wrap.appendChild(head);
  var title=document.createElement('div');title.className='pc-title';title.textContent=card.title;wrap.appendChild(title);
  var pbody=document.createElement('div');pbody.className='pc-body';pbody.innerHTML=esc(card.body).replace(/\n/g,'<br>');wrap.appendChild(pbody);
  var kw=encodeURIComponent(card.title);
  var sitemap={
    hotels:[
      {name:'じゃらん',     url:'https://www.jalan.net/yad_search/?kwd='+kw},
      {name:'楽天トラベル', url:'https://travel.rakuten.co.jp/keyword/'+kw+'/'},
      {name:'一休.com',    url:'https://www.ikyu.com/search/?word='+kw},
      {name:'Agoda',       url:'https://www.agoda.com/ja-jp/search?city='+kw},
      {name:'Booking.com', url:'https://www.booking.com/search.ja.html?ss='+kw},
    ],
    sightseeing:[
      {name:'Google Maps',  url:'https://www.google.com/maps/search/'+kw},
      {name:'じゃらん観光', url:'https://www.jalan.net/kankou/search/?keyword='+kw},
      {name:'トリップ',     url:'https://www.tripadvisor.jp/Search?q='+kw},
    ],
    restaurants:[
      {name:'食べログ',       url:'https://tabelog.com/search/?vs=1&sk='+kw},
      {name:'ぐるなび',      url:'https://r.gnavi.co.jp/search/?query='+kw},
      {name:'ホットペッパー', url:'https://www.hotpepper.jp/CSP/psh010/?keyword='+kw},
      {name:'トリップ',       url:'https://www.tripadvisor.jp/Search?q='+kw},
    ],
    transport:[
      {name:'Yahoo!乗換',  url:'https://transit.yahoo.co.jp/search/result?flatlon=&to='+kw},
      {name:'Google Maps', url:'https://www.google.com/maps/dir/?api=1&destination='+encodeURIComponent(decodeURIComponent(kw))},
    ],
  };
  var sites=sitemap[card.type]||[];
  if(sites.length){var links=document.createElement('div');links.className='pc-links';sites.forEach(function(s){var a=document.createElement('a');a.className='pc-link';a.href=s.url;a.target='_blank';a.rel='noopener';a.innerHTML='<i class="ti ti-external-link" style="font-size:10px"></i> '+s.name;links.appendChild(a);});wrap.appendChild(links);}
  var foot=document.createElement('div');foot.className='pc-foot';
  var saveBtn=document.createElement('button');saveBtn.className='pc-save';saveBtn.innerHTML='<i class="ti ti-plus" style="font-size:12px"></i> プランに追加';
  var resBtn=document.createElement('button');resBtn.className='pc-res-btn';resBtn.innerHTML='<i class="ti ti-calendar-plus" style="font-size:12px"></i> 予約に登録';
  var skipBtn=document.createElement('button');skipBtn.className='pc-skip';skipBtn.textContent='スキップ';
  foot.appendChild(saveBtn);foot.appendChild(resBtn);foot.appendChild(skipBtn);wrap.appendChild(foot);
  saveBtn.addEventListener('click',function(){addPlanItem(card.type,card.title,card.body);saveBtn.innerHTML='<i class="ti ti-check" style="font-size:12px"></i> 追加しました';saveBtn.disabled=true;saveBtn.style.background='#16a34a';skipBtn.style.display='none';resBtn.style.display='none';});
  resBtn.addEventListener('click',function(){openResModal({type:card.type,title:card.title,location:''},null);});
  skipBtn.addEventListener('click',function(){wrap.remove();});
  return wrap;
}

/* ── 予約管理 ── */
var RES_BADGE={hotels:{style:'background:#faeeda;color:#854f0b',label:'ホテル',icon:'ti-building'},restaurants:{style:'background:#eaf3de;color:#3b6d11',label:'グルメ',icon:'ti-tools-kitchen-2'},sightseeing:{style:'background:#e6f1fb;color:#185fa5',label:'観光',icon:'ti-map-2'},transport:{style:'background:#eeedfe;color:#534ab7',label:'交通',icon:'ti-train'},other:{style:'background:#f0ede8;color:#6b6a65',label:'予約',icon:'ti-calendar-event'}};
var resEditId=null;

ge('resAddBtn').addEventListener('click',function(){openResModal(null,null);});
ge('resModal').addEventListener('click',function(e){if(e.target===ge('resModal'))closeResModal();});
ge('resSaveBtn').addEventListener('click',saveReservation);

function openResModal(prefill, editId){
  resEditId=editId||null;
  ge('resModalTitle').textContent=editId?'予約を編集':'予約を登録';
  ge('resSaveBtn').textContent=editId?'更新する':'登録する';
  ge('resType').value=(prefill&&prefill.type)||'hotels';
  ge('resTitle').value=(prefill&&prefill.title)||'';
  ge('resLocation').value=(prefill&&prefill.location)||'';
  ge('resUrl').value=(prefill&&prefill.url)||'';
  ge('resStartDate').value=(prefill&&prefill.start_date)||'';
  ge('resStartTime').value=(prefill&&prefill.start_time)||'';
  ge('resEndDate').value=(prefill&&prefill.end_date)||'';
  ge('resEndTime').value=(prefill&&prefill.end_time)||'';
  ge('resMemo').value=(prefill&&prefill.memo)||'';
  ge('resEditId').value=editId||'';
  ge('res-modal-err').style.display='none';
  ge('resModal').classList.add('on');
}
function closeResModal(){ ge('resModal').classList.remove('on'); }

function saveReservation(){
  var title=ge('resTitle').value.trim();
  if(!title){ge('res-modal-err').textContent='名前を入力してください';ge('res-modal-err').style.display='block';return;}
  var url=ge('resUrl').value.trim();
  if(url&&!/^https?:\/\//i.test(url)){ge('res-modal-err').textContent='URLはhttpまたはhttpsで始めてください';ge('res-modal-err').style.display='block';return;}
  var editId=ge('resEditId').value;
  var action=editId?'update':'save';
  var body={
    type:ge('resType').value, title:title,
    location:ge('resLocation').value.trim(),
    url:url, conv_id:convId,
    start_date:ge('resStartDate').value, start_time:ge('resStartTime').value,
    end_date:ge('resEndDate').value, end_time:ge('resEndTime').value,
    memo:ge('resMemo').value.trim(),
  };
  if(editId) body.id=parseInt(editId);
  apiFetch('api/reservations.php?action='+action,{method:'POST',body:JSON.stringify(body)})
    .then(function(r){return r.json();}).then(function(d){
      if(!d.ok){ge('res-modal-err').textContent=d.error||'保存に失敗しました';ge('res-modal-err').style.display='block';return;}
      closeResModal();
      loadReservations();
    });
}

function loadReservations(){
  apiFetch('api/reservations.php?action=list&conv_id='+convId)
    .then(function(r){return r.json();}).then(function(list){
      renderReservations(list);
    }).catch(function(){});
}

function renderReservations(list){
  var wrap=ge('res-list-wrap'); wrap.innerHTML='';
  var empty=ge('empty_reservations');
  var cnt=ge('cnt_reservations');
  if(!list||!list.length){
    if(empty)empty.style.display='';
    if(cnt){cnt.textContent='0';cnt.style.background='var(--bg3)';cnt.style.color='var(--t2)';}
    return;
  }
  if(empty)empty.style.display='none';
  if(cnt){cnt.textContent=list.length;cnt.style.background='var(--a1)';cnt.style.color='#fff';}

  // 日付でグループ化
  var groups={};
  list.forEach(function(r){
    var key=r.start_date||'日付未定';
    if(!groups[key])groups[key]=[];
    groups[key].push(r);
  });

  Object.keys(groups).sort().forEach(function(dateKey){
    var gDiv=document.createElement('div');gDiv.style.marginBottom='14px';
    var label=document.createElement('div');label.className='day-label';
    if(dateKey==='日付未定'){label.textContent='日付未定';}
    else{
      var d=new Date(dateKey+'T00:00:00');
      var dow=['日','月','火','水','木','金','土'][d.getDay()];
      label.textContent=(d.getMonth()+1)+'月'+d.getDate()+'日（'+dow+'）';
    }
    gDiv.appendChild(label);

    groups[dateKey].forEach(function(r){
      var b=RES_BADGE[r.type]||RES_BADGE.other;
      var card=document.createElement('div');card.className='res-card';
      var timeHtml='<div class="res-time"><div class="res-time-main">'+(r.start_time?r.start_time.slice(0,5):'--:--')+'</div><div class="res-time-sub">'+b.label+'</div></div>';
      var noteLines=[];
      if(r.location)noteLines.push('📍 '+esc(r.location));
      if(r.end_date||r.end_time){
        var endStr='〜 ';
        if(r.end_date&&r.end_date!==r.start_date){var ed=new Date(r.end_date+'T00:00:00');endStr+=(ed.getMonth()+1)+'/'+ed.getDate()+' ';}
        if(r.end_time)endStr+=r.end_time.slice(0,5);
        noteLines.push(endStr);
      }
      if(r.memo)noteLines.push(esc(r.memo).replace(/\n/g,' '));
      var urlHtml=r.url?'<a href="'+esc(r.url)+'" target="_blank" rel="noopener" class="res-url"><i class="ti ti-external-link" style="font-size:10px"></i> 予約サイト</a>':'';
      card.innerHTML=timeHtml
        +'<div class="res-divider"></div>'
        +'<div class="res-body">'
        +'<span class="pc-badge" style="'+b.style+'"><i class="ti '+b.icon+'" style="font-size:10px"></i> '+b.label+'</span> '
        +'<div class="res-name">'+esc(r.title)+'</div>'
        +(noteLines.length?'<div class="res-note">'+noteLines.join('　')+'</div>':'')
        +urlHtml
        +'</div>'
        +'<div class="res-actions">'
        +'<button class="res-btn" title="編集"><i class="ti ti-edit" style="font-size:13px"></i></button>'
        +'<button class="res-btn del" title="削除"><i class="ti ti-trash" style="font-size:13px"></i></button>'
        +'</div>';
      card.querySelectorAll('.res-btn')[0].addEventListener('click',function(){openResModal(r,r.id);});
      card.querySelectorAll('.res-btn')[1].addEventListener('click',function(){
        if(!confirm('削除しますか？'))return;
        apiFetch('api/reservations.php?action=delete',{method:'POST',body:JSON.stringify({id:r.id})})
          .then(function(){loadReservations();});
      });
      gDiv.appendChild(card);
    });
    wrap.appendChild(gDiv);
  });
}

function scrollDown(){ge('bottom').scrollIntoView({behavior:'smooth'});}
window.send=send;
})();
</script>
</body>
</html>