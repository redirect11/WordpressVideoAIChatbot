import React from 'react';
import Chatbot from './components/Chatbot'; // Assicurati che il percorso sia corretto!

function App() {
    return (
        <div className="App">
            <h1>Benvenuto nel Nostro Sito</h1>
            {/* Includi il componente Chatbot dove desideri che appaia nell'UI */}
            <Chatbot />
        </div>
    );
}

export default App;