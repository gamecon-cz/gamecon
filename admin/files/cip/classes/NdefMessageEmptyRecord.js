/**
 * https://developer.chrome.com/articles/nfc/#read-and-write-an-empty-record
 */
class NdefMessageEmptyRecord extends NdefMessageRecord {

  static get recordType() {
    return RECORD_TYPE_EMPTY;
  }

  constructor() {
    super({recordType: 'empty'});
  }

}
