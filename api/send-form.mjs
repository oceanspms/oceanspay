const fields = {
  name: 'Name', email: 'Work email', company: 'Company',
  organisationType: 'Organisation type', payrollCount: 'Payrolls managed',
  monthlyVolume: 'Monthly volume', currentSoftware: 'Current software',
  'areas[]': 'Areas of interest', message: 'Message',
};

const finish = (status) => new Response(null, {
  status: 303,
  headers: { Location: `/contact.html?lead=${status}#demo-form`, 'Cache-Control': 'no-store' },
});

export default {
  async fetch(request) {
    if (request.method !== 'POST') {
      return new Response('Method not allowed', { status: 405, headers: { Allow: 'POST' } });
    }

    try {
      const type = request.headers.get('content-type')?.split(';')[0].trim();
      if (type !== 'application/x-www-form-urlencoded') return finish('error');
      if (Number(request.headers.get('content-length')) > 32768) return finish('error');
      const body = await request.arrayBuffer();
      if (body.byteLength > 32768) return finish('error');
      const form = new URLSearchParams(new TextDecoder().decode(body));
      if (form.get('website')?.trim()) return finish('error');

      const name = form.get('name')?.trim() ?? '';
      const email = form.get('email')?.trim() ?? '';
      const company = form.get('company')?.trim() ?? '';
      const organisation = form.get('organisationType')?.trim() ?? '';
      if (!name || name.length > 120 || !company || !organisation || email.length > 254 || !/^[^\s@<>]+@[^\s@<>]+\.[^\s@<>]+$/.test(email)) {
        return finish('error');
      }

      const key = process.env.RESEND_API_KEY?.trim();
      if (!key) {
        console.error('Demo form: RESEND_API_KEY is not configured');
        return finish('error');
      }

      const textContent = Object.entries(fields).map(([field, label]) => {
        const values = form.getAll(field).slice(0, 12).map(value => value.trim().slice(0, 4000)).filter(Boolean);
        return `${label}: ${values.join(', ') || '(not provided)'}`;
      }).join('\n\n');

      const result = await fetch('https://api.resend.com/emails', {
        method: 'POST',
        redirect: 'error',
        headers: { Authorization: `Bearer ${key}`, 'Content-Type': 'application/json', Accept: 'application/json' },
        signal: AbortSignal.timeout(10000),
        body: JSON.stringify({
          from: 'Oceans Pay Website <info@oceanspay.co.uk>',
          to: ['palkesh@oceanspay.co.uk'],
          reply_to: email,
          subject: '[Oceans Pay] New Book a Demo request',
          text: textContent,
        }),
      });
      if (!result.ok) {
        const data = await result.json().catch(() => null);
        const code = typeof data?.name === 'string' && /^[a-z_]{1,64}$/i.test(data.name) ? data.name : 'unknown';
        console.error('Demo form: Resend rejected the request', result.status, code);
        return finish('error');
      }
      const data = await result.json();
      if (typeof data?.id !== 'string' || !data.id) {
        console.error('Demo form: Resend did not return a message ID');
        return finish('error');
      }
      return finish('sent');
    } catch {
      console.error('Demo form: could not process request or reach Resend');
      return finish('error');
    }
  },
};
