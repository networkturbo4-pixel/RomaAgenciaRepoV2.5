fetch('http://localhost:3001/api/send', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ number: '51902595959@c.us', message: 'Prueba desde script' })
}).then(res => res.json()).then(console.log).catch(console.error);
