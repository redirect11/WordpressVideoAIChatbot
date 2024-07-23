// src/App.js
import React, { useEffect, useState } from 'react';
import Chatbot, { createChatBotMessage, createClientMessage, createCustomMessage } from 'react-chatbot-kit';
import config from '../config';
import MessageParser from '../messageParser';
import ActionProvider from '../actionProvider';
import 'react-chatbot-kit/build/main.css'
import SVG from 'react-inlinesvg'; 



const ChatbotStyleWrapper = (props) => {
  return (
    <div className="chatbot-custom-theme" style={{ width: '100%', height: '100%' }}>
      <Chatbot {...props} />
    </div>
  );
}

const VideoAiChatbot = () => {
  const [isChatbotOpen, setIsChatbotOpen] = useState(false);
  const [assistants, setAssistants] = useState(window.ChatbotData.assistants.data ? window.ChatbotData.assistants.data: []);

  let oldMessages = window.ChatbotData.messages.data.data ? window.ChatbotData.messages.data.data : [];
  
  
  let messageHistory = [];
  for(let i = 0; i < oldMessages.length; i++) {
    console.log('oldMessages[i]:', oldMessages[i]);
    if(oldMessages[i].role === "user" && !(oldMessages[i].metadata?.postprompt === "true"	)) {
      messageHistory.push(createClientMessage(oldMessages[i].content[0].text.value));
      //continue;
    } else if (oldMessages[i].role === "assistant" && oldMessages[i].assistant_id) {
      messageHistory.push(createCustomMessage(oldMessages[i].content[0].text.value,
                                              "customWithLinks", 
                                              {payload: oldMessages[i].content[0].text.value}));
    }
  }

  const [messages, setMessages] = useState(messageHistory.reverse());

  let chatbotName = window.ChatbotData.chatbotName ? window.ChatbotData.chatbotName : "Chatbot";
  let chatBotMessage;
  let wMessage = window.ChatbotData.welcomeMessage ? window.ChatbotData.welcomeMessage : "";
  console.log('messages:', messages);
  if(assistants.length === 0) {
    chatBotMessage = createChatBotMessage("No assistants available");
  } else if(assistants.length === 1) {
    chatBotMessage = createChatBotMessage(wMessage);
    console.log('assistants:', assistants);	
  } else {	
    chatBotMessage = createChatBotMessage(wMessage,
    {
      widget: "overview",
      delay: null,
      loading: true
    });
  }



  console.log('messageHistory:', messageHistory);

  const updatedConfig = {
      ...config,
      initialMessages: [...messages, chatBotMessage],
      botName: chatbotName,
      state : {
        assistants:  assistants ? assistants : [],
        selectedAssistant: assistants && assistants.length === 1 ? assistants[0].id : "",
      }
  };

  const toggleChatbot = () => {
      setIsChatbotOpen(!isChatbotOpen);
  };

  const validator = (input) => {
    if (!input.replace(/\s/g, '').length) //check if only composed of spaces
      return false;
    if (input.length > 1) //check if the message is empty
      return true;
    return false
  }

  const saveMessages = (messages, HTMLString) => {                        
    console.log('messages:', messages);
    console.log('HTMLText:', HTMLString); 
    setMessages(messages); 
  };

  return (
    <>
      <button
          className="chatbot-toggle-button"
          onClick={toggleChatbot}
      >
        { isChatbotOpen ? 
          <i className="fas fa-times"></i> : 
          <SVG
            src={window.ChatbotData.icon ? window.ChatbotData.icon : "https://upload.wikimedia.org/wikipedia/commons/a/a7/React-icon.svg"}
            width={24}
            height="auto"
            title="React"
          /> }
      </button>
      <div id="chatbot-container" className={isChatbotOpen ? 'chatbot-open' : ''}>
          {isChatbotOpen && (
              <ChatbotStyleWrapper
                  config={updatedConfig}
                  actionProvider={ActionProvider}
                  messageParser={MessageParser}
                  validator={validator}
                  headerText={chatbotName}
                  saveMessages={saveMessages}
              />
          )}
      </div>
  </>
  );
}

export default VideoAiChatbot;