// Local-only HTTPS front for `php -S` and the Next.js server, used by
// scripts/wp-sqlite-site.sh.
// Usage: node scripts/tls-proxy.mjs <listen-port> <upstream-port> <cert> <key>
import { readFileSync } from 'node:fs';
import { request } from 'node:http';
import { createServer } from 'node:https';

const [listenPort, upstreamPort, cert, key] = process.argv.slice(2);

// Hop-by-hop headers apply to one connection and must not be forwarded.
const HOP_BY_HOP = new Set([
  'connection',
  'keep-alive',
  'proxy-connection',
  'transfer-encoding',
  'upgrade',
  'te',
  'trailer',
]);

function endToEnd(headers) {
  return Object.fromEntries(
    Object.entries(headers).filter(
      ([name]) => !HOP_BY_HOP.has(name.toLowerCase()),
    ),
  );
}

createServer(
  { cert: readFileSync(cert), key: readFileSync(key) },
  (incoming, outgoing) => {
    const upstream = request(
      {
        host: '127.0.0.1',
        port: Number(upstreamPort),
        method: incoming.method,
        path: incoming.url,
        headers: {
          ...endToEnd(incoming.headers),
          'x-forwarded-proto': 'https',
          // Append the connecting address, as real reverse proxies do.
          'x-forwarded-for': [
            incoming.headers['x-forwarded-for'],
            incoming.socket.remoteAddress,
          ]
            .filter(Boolean)
            .join(', '),
        },
      },
      (response) => {
        outgoing.writeHead(
          response.statusCode ?? 502,
          endToEnd(response.headers),
        );
        response.pipe(outgoing);
      },
    );
    upstream.on('error', () => {
      if (!outgoing.headersSent) outgoing.writeHead(502);
      outgoing.end();
    });
    incoming.on('aborted', () => upstream.destroy());
    incoming.pipe(upstream);
  },
).listen(Number(listenPort), '127.0.0.1');
