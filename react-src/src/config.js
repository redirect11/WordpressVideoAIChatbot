// src/config.js
import React from 'react';
import Overview from './components/widgets/Overview/Overview';
import { createChatBotMessage, createCustomMessage } from "react-chatbot-kit";

import ChatbotMessageWithLinks from './components/widgets/VideoLink';
import LoaderMessage from './components/widgets/LoaderMessage';
import WithAvatar from './components/widgets/WithAvatar';
import ChatbotHeader from './components/widgets/ChatbotHeader';
import ChatbotMessageWithError from './components/widgets/ErrorMessage';

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
  customComponents: {
    //header : (props) => <ChatbotHeader {...props} name="" />,
    botAvatar: (props) => <WithAvatar {...props} />,
  },
  customMessages: { 
                    customWithLinks: (props) => <ChatbotMessageWithLinks {...props} message={props.state.messages.find(msg => (msg.payload === props.payload))}/>,
                    customWithError: (props) => <ChatbotMessageWithError {...props} message={props.state.messages.find(msg => (msg.payload === props.payload))}/>,
                    loaderMessage: (props) => <LoaderMessage {...props} />,
                  }
};

export default config;