import { configureStore, combineReducers } from '@reduxjs/toolkit'
import assistantsReducer from './slices/AssistantsSlice';
import transcriptionsReducer from './slices/TranscriptionsSlice';

const rootReducer = combineReducers({
    assistants: assistantsReducer,
    transcriptions: transcriptionsReducer
});

const store = configureStore({
    reducer: {rootReducer}
});

export default store;
