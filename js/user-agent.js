/** Following code Copyright (c) 2015, Jonathan Frederic
  https://github.com/jdfreder/pingjs/blob/master/ping.js
  Copyright (c) 2015, Jonathan Frederic
  All rights reserved.

  Redistribution and use in source and binary forms, with or without
  modification, are permitted provided that the following conditions are met:

  * Redistributions of source code must retain the above copyright notice, this
    list of conditions and the following disclaimer.

  * Redistributions in binary form must reproduce the above copyright notice,
    this list of conditions and the following disclaimer in the documentation
    and/or other materials provided with the distribution.

  * Neither the name of pingjs nor the names of its
    contributors may be used to endorse or promote products derived from
    this software without specific prior written permission.

  THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
  AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
  IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
  DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
  FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
  DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
  SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
  CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
  OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
  OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

/**
   * Creates and loads an image element by url.
   * @param  {String} url
   * @return {Promise} promise that resolves to an image element or
   *                   fails to an Error.
   */
function request_image(url) {
  return new Promise(function (resolve, reject) {
    var img = new Image();
    img.onload = function () { resolve(img); };
    img.onerror = function () { reject(url); };
    img.src = url + '?random-no-cache=' + Math.floor((1 + Math.random()) * 0x10000).toString(16);
  });
}

/**
 * Pings a url.
 * @param  {String} url
 * @param  {Number} multiplier - optional, factor to adjust the ping by.  0.3 works well for HTTP servers.
 * @return {Promise} promise that resolves to a ping (ms, float).
 */
async function ping(url, multiplier) {
    return new Promise(function(resolve, reject) {
        var start = (new Date()).getTime();
        var response = function() {
            var delta = ((new Date()).getTime() - start);
            delta *= (multiplier || 1);
            resolve(delta);
        };
        request_image(url).then(response).catch(response);

        // Set a timeout for max-pings, 5s.
        setTimeout(function() { reject(Error('Timeout')); }, 5000);
    });
}

/**
 * Following code Copyright (c) 2024 Aurélien Pierre
 */

// NOTE: window.RTCPeerConnection is "not a constructor" in FF22/23
var RTCPeerConnection = window.RTCPeerConnection || window.webkitRTCPeerConnection || window.mozRTCPeerConnection;
// 'browser' only exists inside WebExtensions: referencing it bare threw a
// ReferenceError in every normal browser, killing this whole script — and with it
// the local_ip promise and the form (fixed 2026-09-29).
var DNSResolver = (typeof browser !== 'undefined' && browser.dns) || (typeof chrome !== 'undefined' && chrome.dns) || null;
var promiseResolve, promiseReject;

var local_ip = new Promise(function(resolve, reject){
  promiseResolve = resolve;
  promiseReject = reject;
});
// Modern browsers hide local IPs behind mDNS names: make sure the promise always
// resolves so the form's required field is never left empty (0.0.0.0 = unknown).
setTimeout(function(){ promiseResolve('0.0.0.0'); }, 3000);

if (RTCPeerConnection) {
  var rtc = new RTCPeerConnection({ iceServers: [] });

  if (1 || window.mozRTCPeerConnection) {      // FF [and now Chrome!] needs a channel/stream to proceed
    rtc.createDataChannel('', {reliable:false});
  };

  rtc.onicecandidate = async function (evt) {
    // convert the candidate to SDP so we can run it through our general parser
    // see https://twitter.com/lancestout/status/525796175425720320 for details
    if (evt.candidate) grepSDP("a=" + evt.candidate.candidate);
  };

  rtc.createOffer(async function (offerDesc) {
    grepSDP(offerDesc.sdp);
    rtc.setLocalDescription(offerDesc);
  }, function (e) { console.warn("offer failed", e); });

  function resolved(record) {
    console.log(record.canonicalName);
    console.log(record.addresses);
  }

  async function updateDisplay(newAddr, addrs) {
    var valid = false;
    if (newAddr in addrs || newAddr == "0.0.0.0")
      return;
    else {
      return ping('https://' + newAddr).then(function(delta) {
        //console.log('Ping time was ' + String(delta) + ' ms');
        addrs.push(newAddr);
      }).catch(function(err) {
        console.warn('Could not ping remote URL', err);
      });
    }
  }

  async function grepSDP(sdp) {
    let lines = sdp.split('\r\n');
    var addrs = [];
    for(line of lines) {
      // c.f. http://tools.ietf.org/html/rfc4566#page-39
      if (~line.indexOf("a=candidate")) {   // http://tools.ietf.org/html/rfc4566#section-5.13
        let parts = line.split(' ');        // http://tools.ietf.org/html/rfc5245#section-15.1
        let addr = parts[4];
        let type = parts[7];
        if (type === 'host') {
          await updateDisplay(addr, addrs);
        }
      }
      else if (~line.indexOf("c=")) {       // http://tools.ietf.org/html/rfc4566#section-5.7
        let parts = line.split(' ');
        let addr = parts[2];
        await updateDisplay(addr, addrs);
      }
    }
    if (addrs[0]) {
      if (DNSResolver) {
        let resolving = DNSResolver.resolve("https://" + addrs[0], [
          "bypass_cache",
          "canonical_name",
        ]);
        resolving.then(resolved)
      }
      promiseResolve(addrs[0]);
    }
  }
}

async function get_local_ip() {
  return new Promise(function(resolve, reject) {
    resolve(local_ip);
  });
}

async function validate_contact(url) {
  // Fetch JSON from ../user-agent.php
  fetch(url)
    .then(res => res.json())
    .then(data => {
      document.getElementById("os").value = data["OS"];
      document.getElementById("browser").value = data["browser"];
      document.getElementById("lang").value = data["lang"];

      document.getElementById("ip").value = data["ip"];
      document.getElementById("country").value = data["country"];
      document.getElementById("isp").value = data["isp"];
    });

  const current_url = new URL(window.location.href);
  document.querySelector('input[name="utm"]').value = current_url.searchParams.get("utm_source");
  document.querySelector('input[name="return_to"]').value = window.location.href;

  local_ip.then(ip => { document.getElementById("localip").value = ip; });

  document.getElementById("contact-send").disabled = false;
}
