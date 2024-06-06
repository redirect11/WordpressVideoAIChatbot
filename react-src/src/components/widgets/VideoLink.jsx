import React from 'react';

import Markdown from 'react-markdown'

import './ChatbotMessage.css';

const ChatbotMessageWithLinks = ( {state, message} ) => {
  //console.log('withAvatar:', withAvatar);
  // const lastMessageIndex = props.state.messages.length - 1; 
  //  if(lastMessageIndex<0){
  //      return null;
  //  }
   //console.log('props:', props);
   const customContent = message.message.value;
   console.log('message:', message);
   console.log('state:', state);
   console.log('customContent:', customContent);
   return (
    <div className="react-chatbot-kit-chat-bot-custom-message-container">
        <div className="react-chatbot-kit-chat-bot-custom-message">
          <Markdown>{customContent}</Markdown>
        </div>
    </div>
   );
 };

export default ChatbotMessageWithLinks;
