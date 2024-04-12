<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebRTC Video Chat with Text</title>
</head>
<body>
    <h1>WebRTC Video Chat with Text</h1>
    <video id="localVideo" autoplay muted></video>
    <video id="remoteVideo" autoplay></video>
    <textarea id="chatInput" placeholder="Type your message..."></textarea>
    <button onclick="startCall()">Start Call</button>
    <button onclick="endCall()">End Call</button>

    <script>
        const configuration = { iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] };
        const localVideo = document.getElementById('localVideo');
        const remoteVideo = document.getElementById('remoteVideo');
        const chatInput = document.getElementById('chatInput');
        let localStream;
        let peerConnection;
        let socket;

        async function startCall() {
            try {
                localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
                localVideo.srcObject = localStream;
                createPeerConnection();
                startSignaling();
            } catch (error) {
                console.error('Error accessing media devices:', error);
            }
        }

        function createPeerConnection() {
            peerConnection = new RTCPeerConnection(configuration);

            localStream.getTracks().forEach(track => peerConnection.addTrack(track, localStream));

            peerConnection.ontrack = event => {
                remoteVideo.srcObject = event.streams[0];
            };

            peerConnection.onicecandidate = event => {
                if (event.candidate) {
                    // Send ICE candidate to remote peer (via signaling server)
                    sendMessage({ type: 'candidate', candidate: event.candidate });
                }
            };

            // Create a data channel for text chat
            const dataChannel = peerConnection.createDataChannel('chat');
            dataChannel.onmessage = event => {
                // Handle incoming chat messages
                console.log('Received message:', event.data);
            };

            // Send chat messages
            chatInput.addEventListener('input', () => {
                dataChannel.send(chatInput.value);
            });
        }

        async function startSignaling() {
            socket = new WebSocket('ws://your-signaling-server-url'); // Replace with your signaling server URL
            socket.onopen = () => {
                console.log('WebSocket connected');
            };

            socket.onmessage = async event => {
                const message = JSON.parse(event.data);
                if (message.type === 'offer') {
                    await peerConnection.setRemoteDescription(new RTCSessionDescription(message));
                    const answer = await peerConnection.createAnswer();
                    await peerConnection.setLocalDescription(answer);
                    sendMessage({ type: 'answer', answer: answer });
                } else if (message.type === 'answer') {
                    await peerConnection.setRemoteDescription(new RTCSessionDescription(message));
                } else if (message.type === 'candidate') {
                    await peerConnection.addIceCandidate(new RTCIceCandidate(message.candidate));
                }
            };
        }

        function sendMessage(message) {
            socket.send(JSON.stringify(message));
        }

        function endCall() {
            localStream.getTracks().forEach(track => track.stop());
            peerConnection.close();
            if (socket) {
                socket.close();
            }
        }
    </script>
</body>
</html>
