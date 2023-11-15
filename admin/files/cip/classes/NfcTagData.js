class NfcTagData {
  /**
   * @param {NdefReadingEvent} readingEvent
   */
  constructor(readingEvent) {
    this.readingEvent = readingEvent
  }

  * getRecords() {
    for (const record of this.readingEvent.message.records) {
      switch (record.recordType) {
        case NdefMessageMimeRecord.recordType :
          yield new NdefMessageMimeRecord({
            mediaType: record.mediaType,
            data: record.data,
            id: record.id,
          })
          break
        case NdefMessageAbsoluteUrlRecord.recordType :
          yield new NdefMessageAbsoluteUrlRecord({
            url: record.data,
            id: record.id,
          })
          break
        case NdefMessageEmptyRecord.recordType :
          yield new NdefMessageEmptyRecord()
          break
        case NdefMessageExternalRecord.recordType :
          yield new NdefMessageExternalRecord({
            externalRecordType: record.recordType,
            data: record.data,
            id: record.id,
          })
          break
        case NdefMessageSmartPosterRecord.recordType :
          yield new NdefMessageSmartPosterRecord({
            data: record.data,
            encoding: record.encoding,
            lang: record.lang,
            id: record.id,
          })
          break
        case NdefMessageTextRecord.recordType :
          yield new NdefMessageTextRecord({
            data: record.data,
            encoding: record.encoding,
            lang: record.lang,
            id: record.id,
          })
          break
        case NdefMessageUrlRecord.recordType :
          yield new NdefMessageUrlRecord({
            url: record.data,
            id: record.id,
          })
          break
        default :
          throw new Error(`Unsupported record type '${record.recordType}'`)
      }
    }
  }

  get serialNumber() {
    return this.readingEvent.serialNumber
  }
}
