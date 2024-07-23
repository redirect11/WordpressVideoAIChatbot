import React from 'react';

import Markdown from 'react-markdown'
import SVG from 'react-inlinesvg'; 


const ChatbotMessageWithLinks = ( {state, message} ) => {
  console.log('state:', state);
  console.log('message:', message);
  //console.log('withAvatar:', withAvatar);
  // const lastMessageIndex = props.state.messages.length - 1; 
  //  if(lastMessageIndex<0){
  //      return null;
  //  }
   const customContent = message.message;
  return (
    <div className="react-chatbot-kit-chat-bot-custom-message-container">
      <div className="react-chatbot-kit-chat-bot-avatar-container">
      <SVG
            src={window.ChatbotData.icon ? window.ChatbotData.icon : "https://upload.wikimedia.org/wikipedia/commons/a/a7/React-icon.svg"}
            width={24}
            height="auto"
            title="React" />
      </div>
      <div className="react-chatbot-kit-chat-bot-custom-message">
        <Markdown>{customContent}</Markdown>
        <div className="react-chatbot-kit-chat-bot-message-arrow"></div>
      </div>
    </div>
  );
 };

export default ChatbotMessageWithLinks;
