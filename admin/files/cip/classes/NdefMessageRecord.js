const RECORD_TYPE_MIME = 'mime';
const RECORD_TYPE_ABSOLUTE_URL = 'absolute-url';
const RECORD_TYPE_URL = 'url';
const RECORD_TYPE_EMPTY = 'empty';
const RECORD_TYPE_EXTERNAL = 'external';
const RECORD_TYPE_SMART_POSTER = 'smart-poster';
const RECORD_TYPE_TEXT = 'text';

/**
 * https://developer.chrome.com/articles/nfc/#scan
 */
class NdefMessageRecord {
  /**
   * @param {'text'|'url'|'mime'|'absolute-url'|'smart-poster'|'empty'|string} recordType
   * @param {string} id
   * @param {object|string} data
   * @param {string} mediaType MIME type like 'application/json'
   * @param {string} lang
   * @param {string} encoding
   */
  constructor(
    {
      recordType,
      id = undefined,
      data = undefined,
      mediaType = undefined,
      lang = undefined,
      encoding = undefined,
    }
  ) {
    this._recordType = recordType;
    this._id = id;
    this._data = data;
    this._mediaType = mediaType;
    this._lang = lang;
    this._encoding = encoding;
  }

  get recordType() {
    return this._recordType;
  }

  get id() {
    return this._id;
  }

  get data() {
    return this._data;
  }

  get mediaType() {
    return this._mediaType;
  }

  get lang() {
    return this._lang;
  }

  get encoding() {
    return this._encoding;
  }
}
