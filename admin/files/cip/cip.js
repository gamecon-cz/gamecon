/**
 * @return {Promise<void>}
 */
async function readTag() {
  if ("NDEFReader" in window) {
    const ndef = new NDEFReader();
    try {
      await ndef.scan();
      ndef.onreading = event => {
        const decoder = new TextDecoder();
        const nfcTagReadEVent = new CustomEvent('gamecon:nfc-tag-read', {detail: new NfcTagData(event)})
        document.dispatchEvent(nfcTagReadEVent)
        for (const record of event.message.records) {
          consoleLog(Date.now() + ' ' + Object.keys(record).join(',') + ' #')
          consoleLog("Record type:  " + record.recordType);
          consoleLog("MIME type:    " + record.mediaType);
          consoleLog("=== data ===\n" + decoder.decode(record.data));
        }
      }
    } catch (error) {
      consoleLog(error);
    }
  } else {
    consoleLog("Web NFC is not supported-nfc.");
  }
}

async function writeTag() {
  if ("NDEFReader" in window) {
    const ndef = new NDEFReader();
    try {
      const before = Date.now()
      await ndef.write("What Web Can Do Today");
      consoleLog("NDEF message written after " + (Date.now() - before));
    } catch (error) {
      consoleLog(error);
    }
  } else {
    consoleLog("Web NFC is not supported-nfc.");
  }
}

function consoleLog(data) {
  var logElement = document.getElementById('log');
  logElement.innerHTML += data + '\n';
}

document.addEventListener('DOMContentLoaded', function () {
  if (!window.location.href.startsWith('https:')) {
    Array.from(document.getElementsByClassName('requires-https')).forEach(function (element) {
      element.classList.remove('display-none')
    })
  } else {
    if ("NDEFReader" in window) {
      Array.from(document.getElementsByClassName('supported-nfc')).forEach(function (element) {
        element.classList.remove('display-none')
      })
      Array.from(document.getElementsByClassName('unsupported-nfc')).forEach(function (element) {
        element.classList.add('display-none')
      })
    } else {
      Array.from(document.getElementsByClassName('supported-nfc')).forEach(function (element) {
        element.classList.add('display-none')
      })
      Array.from(document.getElementsByClassName('unsupported-nfc')).forEach(function (element) {
        element.classList.remove('display-none')
      })
    }
  }
})
