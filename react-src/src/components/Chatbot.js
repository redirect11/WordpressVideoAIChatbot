import React, { useState } from 'react';

function Chatbot() {
    const [isOpen, setIsOpen] = useState(false);

    const toggleChatbot = () => {
        setIsOpen(!isOpen);
    };

    return (
        <div id="chatbot-container" className={isOpen ? 'chatbot-open' : 'chatbot-closed'}>
            <div id="chatbot-header" onClick={toggleChatbot}>
                <span>Chat con il nostro Assistente</span>
                <button id="chatbot-toggle-btn">
                    {isOpen ? '-' : '+'}
                </button>
            </div>
            {isOpen && (
                <div id="chatbot-body">
                    {/* Qui puoi inserire il contenuto del tuo chatbot, ad esempio un iframe o un altro componente */}
                    <div>Contenuto del Chatbot...</div>
                </div>
            )}
        </div>
    );
}

export default Chatbot;