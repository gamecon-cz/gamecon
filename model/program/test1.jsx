
var Greeting = React.createClass({
  render: function() {

    // příklad zavolání všech notifikací
    programData.notifikace.forEach(function(notifikace) { notifikace() });

    return (
      <p>Hello, Universe</p>
    )
  }
});

ReactDOM.render(
  <Greeting/>,
  document.getElementById(programData.elementId)
);
