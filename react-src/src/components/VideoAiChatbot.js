// src/App.js
import React, { useEffect, useState } from 'react';
import Chatbot, { createChatBotMessage } from 'react-chatbot-kit';
import config from '../config';
import MessageParser from '../messageParser';
import ActionProvider from '../actionProvider';
import 'react-chatbot-kit/build/main.css'

import './VideoAiChatbot.css';	

function VideoAiChatbot() {
  const [isChatbotOpen, setIsChatbotOpen] = useState(false);

  const [welcomeMessage, setWelcomeMessage] = useState('');

  const [assistants, setAssistants] = useState([]);

  useEffect(() => {
    if (window.ChatbotData) {
      console.log(window.ChatbotData);
      const assistantsObj = window.ChatbotData.assistants.data;
      setAssistants(assistantsObj.data);
      setWelcomeMessage(window.ChatbotData.welcomeMessage);
  }
  }, []);

  const updatedConfig = {
      ...config,
      initialMessages: [createChatBotMessage(welcomeMessage ? welcomeMessage : "Welcome!", 
                        {
                          widget: "overview",
                          delay: null,
                          loading: true,
                        })],
      state : {
        assistants:  assistants ? assistants : [],
        selectedAssistant: "",
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

  return (
    <div>
      <button
          className="chatbot-toggle-button"
          onClick={toggleChatbot}
      >
        {isChatbotOpen ? <i className="fas fa-times"></i> : <i className="fas fa-robot"></i>}
      </button>
      <div id="chatbot-container" className={isChatbotOpen ? 'chatbot-open' : ''}>
          {isChatbotOpen && (
              <Chatbot
                  config={updatedConfig}
                  actionProvider={ActionProvider}
                  messageParser={MessageParser}
                  validator={validator}
              />
          )}
      </div>
  </div>
  );
}

export default VideoAiChatbot;