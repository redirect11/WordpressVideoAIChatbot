// src/config.js
import React from 'react';
import Overview from './components/widgets/Overview/Overview';
import { createChatBotMessage, createCustomMessage } from "react-chatbot-kit";

import ChatbotMessageWithLinks from './components/widgets/VideoLink';

const config = {
  initialMessages: [createChatBotMessage(`Hello World`)],
  state: {
    assistants: [],
    selectedAssistant: {},
  },
  botName: "Chatbot",
  widgets: [
    {
      widgetName: "overview",
      widgetFunc: (props) => <Overview {...props} />,
      mapStateToProps: ['assistants', 'selectedAssistant']
    },
  ],
  customMessages: { customWithLinks: (props, state) => <ChatbotMessageWithLinks {...props} message={props.state.messages.find(msg => (msg.payload === props.payload))}/>  },
};

export default config;