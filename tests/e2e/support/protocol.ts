import { createCipheriv, createHash, createHmac, randomBytes } from 'node:crypto';

/** Encrypt a message in the format used by WP Remote Users Sync. */
export function encryptMessage(value: unknown): string {
  const key = createHash('sha256').update('wprus-test-encryption-key', 'utf8').digest();
  const iv = randomBytes(16);
  const cipher = createCipheriv('aes-256-cbc', key, iv);
  const encrypted = Buffer.concat([cipher.update(JSON.stringify(value), 'utf8'), cipher.final()]);
  const signature = createHmac('sha256', 'wprus-test-signature-key')
    .update(Buffer.concat([iv, encrypted])).digest();
  return Buffer.concat([iv, signature, encrypted]).toString('base64').replace(/\//g, '_');
}
