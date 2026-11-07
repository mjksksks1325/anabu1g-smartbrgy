// A local SMTP capture for the browser suite: accepts mail on 127.0.0.1, keeps each message in memory and relays
// nothing, so no email can reach a real inbox.
import net from 'node:net';

function decodeBody(body, encoding) {
    if (/base64/i.test(encoding)) return Buffer.from(body.replace(/\s+/g, ''), 'base64').toString('utf8');
    if (/quoted-printable/i.test(encoding)) {
        const bytes = body.replace(/=\r?\n/g, '').replace(/=([0-9A-F]{2})/gi, (_, hex) => String.fromCharCode(parseInt(hex, 16)));
        return Buffer.from(bytes, 'latin1').toString('utf8');
    }
    return body;
}

/** The plain-text part of a MIME message (or the whole body when it is not multipart). */
function textOf(raw) {
    const [head, ...rest] = raw.split(/\r?\n\r?\n/);
    const body = rest.join('\n\n');
    const boundary = /boundary="?([^";\r\n]+)"?/i.exec(head)?.[1];
    if (!boundary) return decodeBody(body, /Content-Transfer-Encoding:\s*(\S+)/i.exec(head)?.[1] ?? '');
    const parts = body.split(`--${boundary}`).map(part => part.replace(/^\r?\n/, ''));
    const preferred = parts.find(part => /Content-Type:\s*text\/plain/i.test(part)) ?? parts.find(part => /Content-Type:\s*text\/html/i.test(part)) ?? '';
    const [partHead, ...partBody] = preferred.split(/\r?\n\r?\n/);
    return decodeBody(partBody.join('\n\n'), /Content-Transfer-Encoding:\s*(\S+)/i.exec(partHead)?.[1] ?? '');
}

export async function startMailCapture() {
    const messages = [];
    const server = net.createServer(socket => {
        let buffer = '';
        let data = false;
        let lines = [];
        let recipients = [];
        const reply = line => socket.write(`${line}\r\n`);
        reply('220 smartbrgy-browser-suite ESMTP');
        socket.on('data', chunk => {
            buffer += chunk.toString('utf8');
            let index;
            while ((index = buffer.indexOf('\r\n')) !== -1) {
                const line = buffer.slice(0, index);
                buffer = buffer.slice(index + 2);
                if (data) {
                    if (line === '.') {
                        const raw = lines.join('\r\n');
                        const subject = /^Subject:\s*(.*)$/mi.exec(raw)?.[1] ?? '';
                        messages.push({ to: recipients, subject, raw, text: textOf(raw), at: Date.now() });
                        data = false; lines = []; recipients = [];
                        reply('250 OK captured');
                    } else {
                        lines.push(line.startsWith('..') ? line.slice(1) : line);
                    }
                    continue;
                }
                const verb = line.split(' ', 1)[0].toUpperCase();
                if (verb === 'EHLO' || verb === 'HELO') { reply('250-smartbrgy-browser-suite'); reply('250 8BITMIME'); }
                else if (verb === 'MAIL') reply('250 OK');
                else if (verb === 'RCPT') { recipients.push(line.split(':').slice(1).join(':').trim().replace(/[<>]/g, '')); reply('250 OK'); }
                else if (verb === 'DATA') { data = true; reply('354 End data with <CR><LF>.<CR><LF>'); }
                else if (verb === 'QUIT') { reply('221 Bye'); socket.end(); }
                else reply('250 OK');
            }
        });
        socket.on('error', () => {});
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    return {
        port: server.address().port,
        messages,
        /** Messages to this address received after a moment in time. */
        to(address, since = 0) {
            return messages.filter(message => message.at > since && message.to.some(to => to.toLowerCase() === address.toLowerCase()));
        },
        /** Every link in a message's text that matches a pattern. */
        links(message, pattern) {
            return [...new Set((message.text.match(/https?:\/\/[^\s"<>)\]]+/g) ?? []).map(link => link.replace(/&amp;/g, '&')))].filter(link => pattern.test(link));
        },
        close: () => new Promise(resolve => server.close(resolve)),
    };
}
