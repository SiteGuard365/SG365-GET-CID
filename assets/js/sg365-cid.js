(function(){
    'use strict';
    if (!document) return;

    function byId(id){ return document.getElementById(id); }

    var form = byId('sg365-cid-form');
    var getBtn = byId('sg365_get_cid');
    var statusBox = byId('sg365_status');
    var stepEl = byId('sg365_step');
    var timerEl = byId('sg365_timer');
    var resultDiv = byId('sg365_result');
    var timerInt = null;
    var timer = 0;

    function startTimer(){
        timer = 0;
        if (timerEl) timerEl.innerText = '0s';
        if (timerInt) clearInterval(timerInt);
        timerInt = setInterval(function(){
            timer++;
            if (timerEl) timerEl.innerText = timer + 's';
        }, 1000);
    }
    function stopTimer(){
        if (timerInt) clearInterval(timerInt);
        timerInt = null;
    }

    function showError(msg){
        stopTimer();
        if (stepEl) stepEl.innerText = 'Error';
        if (resultDiv) resultDiv.innerHTML = '<div class="sg365-cid-box sg365-cid-error"><strong>Error:</strong> ' + msg + '</div>';
    }

    function showSuccess(cid, remaining, message){
        stopTimer();
        if (stepEl) stepEl.innerText = message || 'Done';
        var html = '<div class="sg365-cid-box sg365-cid-success"><div class="sg365-cid-label">Confirmation ID (CID):</div><div class="sg365-cid-code" id="sg365_cid_text">'+ cid +'</div><button id="sg365_copy_btn" class="button">Copy</button>';
        html += '<p class="sg365-cid-remaining">Remaining: ' + (remaining !== undefined ? remaining : '-') + '</p></div>';
        if (resultDiv) resultDiv.innerHTML = html;
        var copyBtn = document.getElementById('sg365_copy_btn');
        if (copyBtn) {
            copyBtn.addEventListener('click', function(){
                var t = document.getElementById('sg365_cid_text').innerText;
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(t).then(function(){
                        copyBtn.innerText = 'Copied';
                        setTimeout(function(){ copyBtn.innerText = 'Copy'; }, 2000);
                    });
                } else {
                    var el = document.createElement('textarea');
                    el.value = t;
                    document.body.appendChild(el);
                    el.select();
                    try { document.execCommand('copy'); copyBtn.innerText = 'Copied'; }
                    catch(e){ alert('Copy failed'); }
                    document.body.removeChild(el);
                }
            });
        }
    }

    if (!form || !getBtn) return;

    getBtn.addEventListener('click', function(e){
        e.preventDefault();
        var order = document.getElementById('sg365_order_id').value.trim();
        var email = document.getElementById('sg365_email').value.trim();
        var iid = document.getElementById('sg365_iid').value.trim();
        var captcha_answer = document.getElementById('sg365_captcha_answer') ? document.getElementById('sg365_captcha_answer').value.trim() : '';
        var captcha_token = document.getElementById('sg365_captcha_token') ? document.getElementById('sg365_captcha_token').value.trim() : '';

        if (!order || !email || !iid) {
            alert('Please fill all fields');
            return;
        }

        statusBox.style.display = 'block';
        resultDiv.innerHTML = '';
        stepEl.innerText = 'Verifying details...';
        startTimer();

        var data = new FormData();
        data.append('action', 'sg365_get_cid');
        data.append('nonce', SG365_CID.nonce);
        data.append('order_id', order);
        data.append('email', email);
        data.append('iid', iid);
        if (SG365_CID.enable_captcha) {
            data.append('captcha_answer', captcha_answer);
            data.append('captcha_token', captcha_token);
        }

        fetch(SG365_CID.ajax_url, { method: 'POST', credentials: 'same-origin', body: data })
            .then(function(resp){ return resp.json(); })
            .then(function(json){
                if (!json) {
                    showError('Unexpected response from server');
                    return;
                }
                if (json.error) {
                    showError(json.error);
                    return;
                }
                if (json.cid) {
                    showSuccess(json.cid, json.remaining, json.message || json.step);
                    return;
                }
                showError('No CID returned.');
            })
            .catch(function(err){
                showError(err && err.message ? err.message : 'Request failed');
            });
    });
})();
