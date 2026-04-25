/**
 * Also plain text can be used, await ndef.write("Hello World");
 * https://developer.chrome.com/articles/nfc/#read-and-write-a-text-record
 */
class NdefMessageTextRecord extends NdefMessageRecord {

  static get recordType() {
    return RECORD_TYPE_TEXT;
  }

  /**
   * @param {string} data
   * @param {string} lang
   * @param {string} encoding
   * @param {string} id
   */
  constructor(
    {
      data: data,
      lang: lang = undefined,
      encoding: encoding = undefined,
      id: id = undefined,
    }
  ) {
    super({recordType: 'text', data: data, lang: lang, encoding: encoding, id: id});
  }

  get data() {
    const textDecoder = new TextDecoder();
    return textDecoder.decode(this._rawData);
  }

  get _rawData() {
    return super.data
  }
}
