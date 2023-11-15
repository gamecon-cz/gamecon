/**
 * https://developer.chrome.com/articles/nfc/#read-and-write-a-smart-poster-record
 */
class NdefMessageSmartPosterRecord extends NdefMessageRecord {

  static get recordType() {
    return RECORD_TYPE_SMART_POSTER;
  }

  /**
   * @param {Object.<string, NdefMessageRecord>} data
   * @param {string} id
   */
  constructor({data: data, id: id = undefined}) {
    super({recordType: 'smart-poster', data: data, id: id});
  }

  /**
   * Overload this method to put your custom "from data to NdefMessageRecord" logic
   * @return {NdefMessageRecord[]}
   */
  toRecords() {
    const records = []
    for (const item of this._data) {
      if (item instanceof NdefMessageRecord) {
        records.push(item)
      }
    }

    return records;
  }
}
