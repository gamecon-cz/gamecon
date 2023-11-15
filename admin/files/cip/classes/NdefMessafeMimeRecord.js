/**
 * https://developer.chrome.com/articles/nfc/#read-and-write-a-mime-type-record
 */
class NdefMessageMimeRecord extends NdefMessageRecord {

  static get recordType() {
    return RECORD_TYPE_MIME;
  }

  /**
   * @param {string} mediaType
   * @param {ArrayBuffer|DataView,Uint8Array} data
   * @param {ArrayBuffer|DataView,Uint8Array} data
   * @param {string} id
   */
  constructor({mediaType: mediaType, data: data, id: id = undefined}) {
    super({recordType: 'mime', mediaType: mediaType, data: data, id: id});
  }

  /**
   * @return {any|HTMLImageElement}
   */
  get data() {
    if (this.mediaType === "application/json") {
      const textDecoder = new TextDecoder();
      return JSON.parse(textDecoder.decode(this._rawData))
    }
    if (this.mediaType.startsWith('image/')) {
      const blob = new Blob([this._rawData], {type: this.mediaType});
      const imageElement = new Image();
      imageElement.src = URL.createObjectURL(blob);
      return imageElement
    }
    throw Error(`Unsupported record mediaType '${this.mediaType}'`);
  }

  get _rawData() {
    return super.data
  }
}
