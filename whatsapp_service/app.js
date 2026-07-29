const express = require('express');
const cors = require('cors');
const wppconnect = require('@wppconnect-team/wppconnect');
const { Server } = require('socket.io');
const http = require('http');

const app = express();
app.use(cors());
app.use(express.json());

const server = http.createServer(app);
const io = new Server(server, {
  cors: {
    origin: '*',
    methods: ['GET', 'POST']
  }
});

let qrCodeData = null;
let clientStatus = 'DISCONNECTED';
let activeClient = null;

wppconnect.create({
  session: 'roma-session',
  catchQR: (base64Qr, asciiQR, attempts, urlCode) => {
    console.log('QR RECEIVED');
    qrCodeData = urlCode || base64Qr; 
    clientStatus = 'QR_READY';
    io.emit('qr', qrCodeData);
    io.emit('status', clientStatus);
  },
  statusFind: (statusSession, session) => {
    console.log('Status Session: ', statusSession);
    if (statusSession === 'isLogged' || statusSession === 'inChat') {
        clientStatus = 'CONNECTED';
        io.emit('status', clientStatus);
    } else if (statusSession === 'autocloseCalled' || statusSession === 'desconnectedMobile') {
        clientStatus = 'DISCONNECTED';
        io.emit('status', clientStatus);
    }
  },
  headless: true,
  logQR: false,
  autoClose: 0
}).then((client) => {
  activeClient = client;
  start(client);
}).catch((error) => console.log(error));

function start(client) {
  clientStatus = 'CONNECTED';
  io.emit('ready', 'WhatsApp is ready!');
  io.emit('status', clientStatus);

  client.onMessage((msg) => {
    io.emit('message', {
      id: msg.id,
      from: msg.from,
      body: msg.body,
      timestamp: msg.timestamp || msg.t,
      fromMe: msg.fromMe
    });
  });
  
  client.onStateChange((state) => {
    console.log('State changed: ', state);
    if (state === 'CONNECTED') clientStatus = 'CONNECTED';
    if (state === 'TIMEOUT') clientStatus = 'DISCONNECTED';
    io.emit('status', clientStatus);
  });
}

io.on('connection', (socket) => {
  console.log('a user connected to socket');
  socket.emit('status', clientStatus);
  if (qrCodeData) {
    socket.emit('qr', qrCodeData);
  }
});

// REST Endpoints
app.get('/api/status', (req, res) => {
  res.json({ status: clientStatus, qr: qrCodeData });
});

app.get('/api/chats', async (req, res) => {
  if (!activeClient || clientStatus !== 'CONNECTED') {
    return res.status(400).json({ error: 'WhatsApp not connected' });
  }
  try {
    const chats = await activeClient.getAllChats();
    const simpleChats = chats.map(chat => ({
      id: chat.id._serialized || chat.id,
      name: chat.name || chat.contact?.name || chat.contact?.pushname || (chat.id._serialized ? chat.id._serialized.split('@')[0] : 'Unknown'),
      isGroup: chat.isGroup,
      unreadCount: chat.unreadCount,
      timestamp: chat.t || chat.timestamp
    }));
    res.json(simpleChats);
  } catch (error) {
    console.error('getChats error:', error);
    res.status(500).json({ error: error.message });
  }
});

app.get('/api/chats/:id/messages', async (req, res) => {
  if (!activeClient || clientStatus !== 'CONNECTED') {
    return res.status(400).json({ error: 'WhatsApp not connected' });
  }
  try {
    const messages = await activeClient.getMessages(req.params.id, { count: 50 });
    const simpleMessages = messages.map(msg => ({
        id: msg.id,
        body: msg.body || msg.caption || '',
        fromMe: msg.fromMe,
        timestamp: msg.timestamp || msg.t
    }));
    res.json(simpleMessages);
  } catch (error) {
    console.error("fetchMessages failed", error.message);
    res.json([]); // Return empty array on error so UI doesn't crash
  }
});

app.post('/api/send', async (req, res) => {
  if (!activeClient || clientStatus !== 'CONNECTED') {
    return res.status(400).json({ error: 'WhatsApp not connected' });
  }
  const { number, message } = req.body;
  if (!number || !message) {
    return res.status(400).json({ error: 'Number and message are required' });
  }
  try {
    const chatId = number.includes('@') ? number : `${number}@c.us`;
    const response = await activeClient.sendText(chatId, message);
    res.json({ success: true, response });
  } catch (error) {
    res.status(500).json({ error: error.message });
  }
});

// Setup for cPanel Passenger OR local dev
if (typeof(PhusionPassenger) !== 'undefined') {
  server.listen('passenger');
} else {
  const PORT = process.env.PORT || 3001;
  server.listen(PORT, () => {
    console.log(`Server listening on port ${PORT}`);
  });
}
