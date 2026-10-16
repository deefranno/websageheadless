// Local-only HTTPS front for `php -S`, used by scripts/wp-sqlite-site.sh.
// Usage: node scripts/tls-proxy.mjs <listen-port> <upstream-port> <cert> <key>
import { readFileSync } from 'node:fs';
import { request } from 'node:http';
import { createServer } from 'node:https';

const [listenPort, upstreamPort, cert, key] = process.argv.slice(2);

createServer(
  { cert: readFileSync(cert), key: readFileSync(key) },
  (incoming, outgoing) => {
    const upstream = request(
      {
        host: '127.0.0.1',
        port: Number(upstreamPort),
        method: incoming.method,
        path: incoming.url,
        headers: { ...incoming.headers, 'x-forwarded-proto': 'https' },
      },
      (response) => {
        outgoing.writeHead(response.statusCode ?? 502, response.headers);
        response.pipe(outgoing);
      },
    );
    upstream.on('error', () => {
      outgoing.writeHead(502).end();
    });
    incoming.pipe(upstream);
  },
).listen(Number(listenPort), '127.0.0.1');
