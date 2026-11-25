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

function brevoFailureReason(status, data) {
  const message = typeof data?.message === 'string' ? data.message : '';
  if (/\bip(?: address)?\b/i.test(message) && /unauthori[sz]ed|unrecogni[sz]ed|not authori[sz]ed|not allowed|unknown|blocked|whitelist/i.test(message)) {
    return 'source_ip_not_authorized';
  }
  if (/access denied for resource|permission|insufficient scope/i.test(message)) {
    return 'insufficient_permissions';
  }
  if (/key not found|invalid (?:api[- ]?)?key|(?:api[- ]?)?key (?:is )?(?:invalid|expired|revoked|inactive|deactivated|missing|not active)|missing (?:api[- ]?)?key/i.test(message)) {
    return 'api_key_rejected';
  }
  return status === 401 || status === 403 ? 'authorization_reason_unspecified' : 'request_rejected';
}

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

      const key = process.env.BREVO_API_KEY?.trim();
      if (!key) {
        console.error('Demo form: BREVO_API_KEY is not configured');
        return finish('error');
      }

      const textContent = Object.entries(fields).map(([field, label]) => {
        const values = form.getAll(field).slice(0, 12).map(value => value.trim().slice(0, 4000)).filter(Boolean);
        return `${label}: ${values.join(', ') || '(not provided)'}`;
      }).join('\n\n');

      const result = await fetch('https://api.brevo.com/v3/smtp/email', {
        method: 'POST',
        redirect: 'error',
        headers: { 'api-key': key, 'Content-Type': 'application/json', Accept: 'application/json' },
        signal: AbortSignal.timeout(10000),
        body: JSON.stringify({
          sender: { name: 'Ocean Across Website', email: 'info@oceanspay.co.uk' },
          to: [{ name: 'Ocean Across', email: 'palkesh@oceanspay.co.uk' }],
          replyTo: { name, email },
          subject: '[Oceans Pay] New Book a Demo request',
          textContent,
          tags: ['website-lead'],
        }),
      });
      if (!result.ok) {
        const data = await result.json().catch(() => null);
        const code = typeof data?.code === 'string' && /^[a-z_]{1,64}$/i.test(data.code) ? data.code : 'unknown';
        console.error('Demo form: Brevo rejected the request', result.status, code, brevoFailureReason(result.status, data));
        return finish('error');
      }
      const data = await result.json();
      if (typeof data?.messageId !== 'string' || !data.messageId) {
        console.error('Demo form: Brevo did not return a message ID');
        return finish('error');
      }
      return finish('sent');
    } catch {
      console.error('Demo form: could not process request or reach Brevo');
      return finish('error');
    }
  },
};
