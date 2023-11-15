/**
 * https://developer.chrome.com/articles/nfc/#read-and-write-a-url-record
 */
class NdefMessageUrlRecord extends NdefMessageRecord {

  static get recordType() {
    return RECORD_TYPE_URL;
  }

  /**
   * @param {string} url
   * @param {string} id
   */
  constructor({url: url, id: id = undefined}) {
    super({recordType: 'url', data: url, id: id});
  }

  get data() {
    const textDecoder = new TextDecoder();
    return textDecoder.decode(this._rawData)
  }

  get _rawData() {
    return super.data
  }
}
