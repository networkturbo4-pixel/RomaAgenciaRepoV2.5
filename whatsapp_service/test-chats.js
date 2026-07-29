fetch('http://localhost:3001/api/chats')
    .then(res => res.json())
    .then(chats => console.log('Loaded chats: ' + chats.length))
    .catch(console.error);
