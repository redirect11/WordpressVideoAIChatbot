import React from "react";

import GeneralOptions from "../options/GeneralOptions/GeneralOptions";

const Overview = (props) => {
    console.log("ciao");
    console.log(props);
  return (
    <div>
      <GeneralOptions actionProvider={props.actionProvider} options={props.assistants} {...props} />
    </div>
  );
};

export default Overview;