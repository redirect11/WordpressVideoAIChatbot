import React from 'react';

import SVG from 'react-inlinesvg'; 


const WithAvatar = ( {state, message} ) => {
  console.log('state:', state);
  console.log('message:', message);
  return (
    <div className="react-chatbot-kit-chat-bot-custom-message-container">
      <div className="react-chatbot-kit-chat-bot-avatar-container">
      <SVG
            src={window.ChatbotData.icon ? window.ChatbotData.icon : "https://upload.wikimedia.org/wikipedia/commons/a/a7/React-icon.svg"}
            width={24}
            height="auto"
            title="React" />
      </div>
    </div>
  );
 };

export default WithAvatar;
