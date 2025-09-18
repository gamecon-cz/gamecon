/**
 * https://developer.chrome.com/articles/nfc/#read-and-write-an-absolute-url-record
 */
class NdefMessageAbsoluteUrlRecord extends NdefMessageRecord {

  static get recordType() {
    return RECORD_TYPE_ABSOLUTE_URL;
  }

  /**
   * @param {string} url
   * @param {string} id
   */
  constructor({url: url, id: id = undefined}) {
    super({recordType: 'absolute-url', data: url, id: id});
  }

  get data() {
    const textDecoder = new TextDecoder();
    return textDecoder.decode(this._rawData)
  }

  get _rawData() {
    return super.data
  }

}
