import type { OutboxItem, OutboxStore } from './outbox'

/** The outbox kept in IndexedDB, so taps (and photos) survive a closed tab or a dead battery. */
export class IndexedDbStore implements OutboxStore {
  private db: Promise<IDBDatabase> | null = null

  constructor(
    private readonly name = 'openwhen',
    private readonly storeName = 'outbox',
  ) {}

  static available(): boolean {
    try {
      return typeof indexedDB !== 'undefined' && indexedDB !== null
    } catch {
      return false
    }
  }

  async all(): Promise<OutboxItem[]> {
    return this.request<OutboxItem[]>('readonly', (s) => s.getAll())
  }

  async put(item: OutboxItem): Promise<void> {
    await this.request('readwrite', (s) => s.put(item))
  }

  async remove(id: string): Promise<void> {
    await this.request('readwrite', (s) => s.delete(id))
  }

  private open(): Promise<IDBDatabase> {
    this.db ??= new Promise((resolve, reject) => {
      const request = indexedDB.open(this.name, 1)
      request.onupgradeneeded = () => {
        if (!request.result.objectStoreNames.contains(this.storeName)) {
          request.result.createObjectStore(this.storeName, { keyPath: 'id' })
        }
      }
      request.onsuccess = () => resolve(request.result)
      request.onerror = () => reject(request.error)
    })
    return this.db
  }

  private async request<T>(mode: IDBTransactionMode, run: (store: IDBObjectStore) => IDBRequest): Promise<T> {
    const db = await this.open()
    return new Promise<T>((resolve, reject) => {
      const tx = db.transaction(this.storeName, mode)
      const request = run(tx.objectStore(this.storeName))
      tx.oncomplete = () => resolve(request.result as T)
      tx.onerror = () => reject(tx.error)
      tx.onabort = () => reject(tx.error)
    })
  }
}
