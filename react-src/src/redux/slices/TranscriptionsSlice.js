import { createSlice } from '@reduxjs/toolkit'

const initialState = {
    transcriptions: [],
    selectedTranscription: null
};

export const transcriptionsSlice = createSlice({
  name: 'transcriptions',
  initialState,
  reducers: {
    updateTranscription: (state, action) => {
      const { transcriptions, newTranscription } = action.payload;
      const updatedTranscriptions = transcriptions.map(transcription => {
          if(transcription.file_id === newTranscription.file_id) {
              return newTranscription;
          } 
        return transcription;
      });
      
      state.transcriptions = updatedTranscriptions;
    },
    deleteTranscription: (state, action) => {
        const { transcriptions, file_id } = action.payload;
        const updated = transcriptions.filter(transcription => transcription.file_id !== file_id);
        state.transcriptions = updated;
    },
    setTranscriptions: (state, action) => {
      state.transcriptions = action.payload;
    },

    setSelectedTranscription: (state, action) => {
      state.selectedTranscription = action.payload;
    },
  },
})

// Action creators are generated for each case reducer function
export const { updateTranscription, deleteTranscription, setTranscriptions, setSelectedTranscription } = transcriptionsSlice.actions

export default transcriptionsSlice.reducer