/**
 * https://developer.chrome.com/articles/nfc/#read-and-write-an-external-type-record
 */
class NdefMessageExternalRecord extends NdefMessageRecord {

  static get recordType() {
    return RECORD_TYPE_EXTERNAL;
  }

  /**
   * @param {string} externalRecordType
   * @param {Object.<string, NdefMessageRecord>} data
   * @param {string} id
   */
  constructor(
    {
      externalRecordType: externalRecordType,
      data: data,
      id: id = undefined,
    }
  ) {
    super({recordType: externalRecordType, data: data, id: id});
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
